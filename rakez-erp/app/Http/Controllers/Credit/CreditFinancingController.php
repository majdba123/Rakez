<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Services\Credit\CreditFinancingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class CreditFinancingController extends Controller
{
    protected CreditFinancingService $financingService;

    public function __construct(CreditFinancingService $financingService)
    {
        $this->financingService = $financingService;
    }

    /**
     * Initialize financing tracker for a reservation.
     * POST /credit/bookings/{id}/financing
     */
    public function initialize(Request $request, int $id): JsonResponse
    {
        try {
            $tracker = $this->financingService->initializeTracker($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم بدء تتبع التمويل بنجاح',
                'data' => $tracker,
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
     * Get financing tracker status.
     * GET /credit/bookings/{id}/financing
     */
    public function show(int $id): JsonResponse
    {
        try {
            $tracker = \App\Models\CreditFinancingTracker::with(['reservation.contract', 'reservation.contractUnit', 'assignedUser'])
                ->where('sales_reservation_id', $id)
                ->firstOrFail();

            $details = $this->financingService->getTrackerDetails($tracker->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب حالة تتبع التمويل بنجاح',
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
     * Complete a financing stage.
     * PATCH /credit/financing/{id}/stage/{stage}
     */
    public function completeStage(Request $request, int $id, int $stage): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => 'required_if:stage,1|nullable|string|max:100',
            'client_salary' => 'nullable|numeric|min:0',
            'employment_type' => 'nullable|in:government,private',
            'appraiser_name' => 'nullable|string|max:255',
        ]);

        try {
            $tracker = $this->financingService->completeStage($id, $stage, $validated, $request->user());

            return response()->json([
                'success' => true,
                'message' => "تم إكمال المرحلة {$stage} بنجاح",
                'data' => $tracker,
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
     * Reject financing.
     * POST /credit/financing/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $tracker = $this->financingService->rejectFinancing($id, $validated['reason'], $request->user());

            return response()->json([
                'success' => true,
                'message' => 'تم رفض طلب التمويل',
                'data' => $tracker,
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

