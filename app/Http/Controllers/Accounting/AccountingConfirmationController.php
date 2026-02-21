<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SalesReservation;
use App\Models\User;
use App\Models\UserNotification;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingConfirmationController extends Controller
{
    /**
     * List pending down payment confirmations.
     * GET /accounting/pending-confirmations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee',
                'deposits',
            ])
                ->pendingAccountingConfirmation();

            // Filter by payment method
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->input('payment_method'));
            }

            // Date range filter
            if ($request->has('from_date')) {
                $query->whereDate('confirmed_at', '>=', $request->input('from_date'));
            }
            if ($request->has('to_date')) {
                $query->whereDate('confirmed_at', '<=', $request->input('to_date'));
            }

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $reservations = $query->orderBy('confirmed_at', 'desc')->paginate($perPage);

            $data = array_map(function (SalesReservation $reservation) {
                $amount = $reservation->down_payment_amount
                    ?? $reservation->deposits->sum('amount');
                $amount = $amount !== null ? (float) $amount : 0;
                return array_merge($reservation->toArray(), [
                    'booking_number' => (string) $reservation->id,
                    'amount' => round($amount, 2),
                ]);
            }, $reservations->items());

            return ApiResponse::success($data, 'تم جلب الحجوزات المعلقة للتأكيد بنجاح', 200, [
                'pagination' => [
                    'total' => $reservations->total(),
                    'count' => $reservations->count(),
                    'per_page' => $reservations->perPage(),
                    'current_page' => $reservations->currentPage(),
                    'total_pages' => $reservations->lastPage(),
                    'has_more_pages' => $reservations->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Confirm a down payment.
     * POST /accounting/confirm/{reservationId}
     */
    public function confirm(Request $request, int $reservationId): JsonResponse
    {
        try {
            $reservation = SalesReservation::findOrFail($reservationId);

            // Validate reservation is pending confirmation
            if ($reservation->down_payment_confirmed) {
                throw new Exception('تم تأكيد العربون مسبقًا');
            }

            if (!in_array($reservation->payment_method, ['bank_transfer', 'bank_financing'])) {
                throw new Exception('هذا الحجز لا يتطلب تأكيد محاسبي');
            }

            DB::beginTransaction();
            try {
                $reservation->update([
                    'down_payment_confirmed' => true,
                    'down_payment_confirmed_by' => $request->user()->id,
                    'down_payment_confirmed_at' => now(),
                ]);

                // Notify credit department
                $this->notifyCreditDepartment($reservation);

                // Notify marketer
                $this->notifyMarketer($reservation);

                DB::commit();

                return ApiResponse::success($reservation->fresh(['contract', 'contractUnit']), 'تم تأكيد استلام العربون بنجاح');
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return ApiResponse::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Get confirmation history.
     * GET /accounting/confirmations/history
     *
     * Each item includes booking_number (reservation id) and amount (down payment or deposits total).
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'downPaymentConfirmedBy',
                'deposits',
            ])
                ->where('down_payment_confirmed', true)
                ->whereNotNull('down_payment_confirmed_at');

            // Date range filter
            if ($request->has('from_date')) {
                $query->whereDate('down_payment_confirmed_at', '>=', $request->input('from_date'));
            }
            if ($request->has('to_date')) {
                $query->whereDate('down_payment_confirmed_at', '<=', $request->input('to_date'));
            }

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $reservations = $query->orderBy('down_payment_confirmed_at', 'desc')->paginate($perPage);

            $data = array_map(function (SalesReservation $reservation) {
                $amount = $reservation->down_payment_amount
                    ?? $reservation->deposits->sum('amount');
                $amount = $amount !== null ? (float) $amount : 0;

                return array_merge($reservation->toArray(), [
                    'booking_number' => (string) $reservation->id,
                    'amount' => round($amount, 2),
                ]);
            }, $reservations->items());

            return ApiResponse::success($data, 'تم جلب سجل التأكيدات بنجاح', 200, [
                'pagination' => [
                    'total' => $reservations->total(),
                    'count' => $reservations->count(),
                    'per_page' => $reservations->perPage(),
                    'current_page' => $reservations->currentPage(),
                    'total_pages' => $reservations->lastPage(),
                    'has_more_pages' => $reservations->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Notify credit department about confirmed down payment.
     */
    protected function notifyCreditDepartment(SalesReservation $reservation): void
    {
        $message = sprintf(
            'تم تأكيد استلام العربون للحجز رقم %d - المشروع: %s - الوحدة: %s',
            $reservation->id,
            $reservation->contract?->project_name ?? '-',
            $reservation->contractUnit?->unit_number ?? '-'
        );

        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => 'down_payment_confirmed',
                'context' => [
                    'reservation_id' => $reservation->id,
                ],
            ]);
        }
    }

    /**
     * Notify marketer about confirmed down payment.
     */
    protected function notifyMarketer(SalesReservation $reservation): void
    {
        if ($reservation->marketing_employee_id) {
            $message = sprintf(
                'تم تأكيد استلام العربون للحجز رقم %d',
                $reservation->id
            );

            UserNotification::create([
                'user_id' => $reservation->marketing_employee_id,
                'message' => $message,
            ]);
        }
    }
}

