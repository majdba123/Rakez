<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Models\CreditFinancingTracker;
use App\Services\Credit\CreditFinancingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;

class CreditFinancingController extends Controller
{
    protected CreditFinancingService $financingService;

    public function __construct(CreditFinancingService $financingService)
    {
        $this->financingService = $financingService;
    }

    /**
     * Return financing state for API without internal tracker id (booking-centric only).
     */
    private function financingForUser(CreditFinancingTracker $tracker): array
    {
        $arr = $tracker->toArray();
        unset($arr['id']);
        $arr['booking_id'] = $tracker->sales_reservation_id;

        return $arr;
    }

    /**
     * Initialize financing tracker for a reservation.
     * POST /credit/bookings/{id}/financing
     */
    public function initialize(Request $request, int $id): JsonResponse
    {
        try {
            $tracker = $this->financingService->initializeTracker($id, $request->user()->id);

            $message = $tracker->is_cash_workflow
                ? 'تم تهيئة متابعة الشراء النقدي بنجاح (مرحلتان خلال 7 أيام تقويمية)'
                : 'تم تهيئة إجراءات المتابعة الائتمانية بنجاح';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $this->financingForUser($tracker),
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Advance to next stage, or initialize financing if none exists.
     * POST /credit/bookings/{id}/financing/advance
     * Single action: employee clicks "نقل للمرحلة التالية" → either initialization or stage completion.
     */
    public function advance(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:100',
            'client_salary' => 'nullable|numeric|min:0',
            'employment_type' => 'nullable|in:government,private',
            'appraiser_name' => 'nullable|string|max:255',
        ]);

        try {
            $result = $this->financingService->advanceOrInitialize($id, $validated, $request->user());

            $fin = $result['financing'];
            if ($result['action'] === 'initialized') {
                $message = $fin->is_cash_workflow
                    ? 'تم تهيئة متابعة الشراء النقدي بنجاح (مرحلتان خلال 7 أيام تقويمية)'
                    : 'تم تهيئة إجراءات المتابعة الائتمانية بنجاح';
            } elseif ($fin->is_cash_workflow) {
                $completed = (int) $result['stage'];
                $message = $completed === 1
                    ? 'تم إكمال مرحلة التواصل مع العميل؛ بدأت مرحلة التجهيز قبل الإفراغ'
                    : ($completed === 6
                        ? 'تم إكمال متابعة الشراء النقدي؛ يمكن متابعة نقل الملكية'
                        : "تم إكمال المرحلة {$completed} بنجاح");
            } else {
                $message = "تم إكمال المرحلة {$result['stage']} بنجاح";
            }

            $data = $result;
            $data['financing'] = $this->financingForUser($result['financing']);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
            ], $result['action'] === 'initialized' ? 201 : 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get financing tracker status.
     * GET /credit/bookings/{id}/financing
     */
    public function show(int $id): JsonResponse
    {
        try {
            $tracker = \App\Models\CreditFinancingTracker::with(['reservation.contract', 'reservation.contractUnit', 'assignedUser'])
                ->where('sales_reservation_id', $id)
                ->first();

            if (!$tracker) {
                return response()->json([
                    'success' => true,
                    'message' => 'لم تبدأ إجراءات المتابعة الائتمانية لهذا الحجز بعد',
                    'data' => null,
                ], 200);
            }

            $details = $this->financingService->getTrackerDetails($tracker->id);
            $details['financing'] = $this->financingForUser($details['financing']);
            $details['booking_id'] = $id;

            return response()->json([
                'success' => true,
                'message' => 'تم جلب حالة المتابعة الائتمانية بنجاح',
                'data' => $details,
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Complete a financing stage. Booking-centric: resolve tracker by booking_id internally.
     * PATCH /credit/bookings/{booking_id}/financing/stage/{stage}
     */
    public function completeStage(Request $request, int $bookingId, int $stage): JsonResponse
    {
        $request->merge(['stage' => (int) $stage]);
        $validated = $request->validate([
            'stage' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6])],
            'bank_name' => 'nullable|string|max:100',
            'client_salary' => 'nullable|numeric|min:0',
            'employment_type' => 'nullable|in:government,private',
            'appraiser_name' => 'nullable|string|max:255',
        ]);

        try {
            $tracker = $this->financingService->getTrackerByReservationId($bookingId);
            $tracker = $this->financingService->completeStage($tracker->id, $stage, $validated, $request->user());

            if ($tracker->is_cash_workflow) {
                $message = $stage === 1
                    ? 'تم إكمال مرحلة التواصل مع العميل؛ بدأت مرحلة التجهيز قبل الإفراغ'
                    : ($stage === 6
                        ? 'تم إكمال متابعة الشراء النقدي؛ يمكن متابعة نقل الملكية'
                        : "تم إكمال المرحلة {$stage} بنجاح");
            } else {
                $message = "تم إكمال المرحلة {$stage} بنجاح";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $this->financingForUser($tracker),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Reject financing. Booking-centric: resolve tracker by booking_id internally.
     * POST /credit/bookings/{booking_id}/financing/reject
     */
    public function reject(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $tracker = $this->financingService->getTrackerByReservationId($bookingId);
            $tracker = $this->financingService->rejectFinancing($tracker->id, $validated['reason'], $request->user());

            $message = $tracker->is_cash_workflow
                ? 'تم رفض متابعة الحجز النقدي'
                : 'تم رفض طلب التمويل';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $this->financingForUser($tracker),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}



