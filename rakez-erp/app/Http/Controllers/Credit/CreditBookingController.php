<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Models\SalesReservation;
use App\Models\SalesWaitingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class CreditBookingController extends Controller
{
    /**
     * Get confirmed bookings for credit.
     * GET /credit/bookings/confirmed
     */
    public function confirmed(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'financingTracker',
                'titleTransfer',
            ])
                ->confirmedForCredit();

            // Filter by credit status
            if ($request->has('credit_status')) {
                $query->byCreditStatus($request->input('credit_status'));
            }

            // Filter by purchase mechanism
            if ($request->has('purchase_mechanism')) {
                $query->where('purchase_mechanism', $request->input('purchase_mechanism'));
            }

            // Filter by down payment confirmation
            if ($request->has('down_payment_confirmed')) {
                $query->where('down_payment_confirmed', filter_var($request->input('down_payment_confirmed'), FILTER_VALIDATE_BOOLEAN));
            }

            // Date range filter
            if ($request->has('from_date')) {
                $query->whereDate('confirmed_at', '>=', $request->input('from_date'));
            }
            if ($request->has('to_date')) {
                $query->whereDate('confirmed_at', '<=', $request->input('to_date'));
            }

            $perPage = min((int) $request->input('per_page', 15), 100);
            $bookings = $query->orderBy('confirmed_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الحجوزات المؤكدة بنجاح',
                'data' => $bookings->items(),
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get negotiation bookings (read-only).
     * GET /credit/bookings/negotiation
     */
    public function negotiation(Request $request): JsonResponse
    {
        try {
            $query = SalesReservation::with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'negotiationApproval',
            ])
                ->where('status', 'under_negotiation')
                ->where('reservation_type', 'negotiation');

            $perPage = min((int) $request->input('per_page', 15), 100);
            $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب حجوزات التفاوض بنجاح',
                'data' => $bookings->items(),
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get waiting bookings (read-only).
     * GET /credit/bookings/waiting
     */
    public function waiting(Request $request): JsonResponse
    {
        try {
            $query = SalesWaitingList::with([
                'contract',
                'contractUnit',
                'salesStaff',
            ])
                ->where('status', 'waiting');

            $perPage = min((int) $request->input('per_page', 15), 100);
            $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب حجوزات الانتظار بنجاح',
                'data' => $bookings->items(),
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get booking details.
     * GET /credit/bookings/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $reservation = SalesReservation::with([
                'contract.info',
                'contractUnit',
                'marketingEmployee.team',
                'financingTracker.assignedUser',
                'titleTransfer.processedBy',
                'claimFile',
                'negotiationApproval',
                'paymentInstallments',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الحجز بنجاح',
                'data' => [
                    // Project Information
                    'project' => [
                        'id' => $reservation->contract?->id,
                        'name' => $reservation->contract?->project_name ?? $reservation->contract?->info?->project_name,
                        'city' => $reservation->contract?->city,
                    ],
                    // Unit Information
                    'unit' => [
                        'id' => $reservation->contractUnit?->id,
                        'number' => $reservation->contractUnit?->unit_number,
                        'type' => $reservation->contractUnit?->type,
                        'area' => $reservation->contractUnit?->area,
                        'price' => $reservation->contractUnit?->price,
                    ],
                    // Client Information
                    'client' => [
                        'name' => $reservation->client_name,
                        'mobile' => $reservation->client_mobile,
                        'nationality' => $reservation->client_nationality,
                        'iban' => $reservation->client_iban,
                    ],
                    // Financial Details
                    'financial' => [
                        'down_payment_amount' => $reservation->down_payment_amount,
                        'down_payment_status' => $reservation->down_payment_status,
                        'down_payment_confirmed' => $reservation->down_payment_confirmed,
                        'payment_method' => $reservation->payment_method,
                        'purchase_mechanism' => $reservation->purchase_mechanism,
                        'brokerage_commission_percent' => $reservation->brokerage_commission_percent,
                        'commission_payer' => $reservation->commission_payer,
                        'tax_amount' => $reservation->tax_amount,
                    ],
                    // Marketing Details
                    'marketing' => [
                        'team_name' => $reservation->marketingEmployee?->team?->name,
                        'marketer_name' => $reservation->marketingEmployee?->name,
                    ],
                    // Status
                    'status' => $reservation->status,
                    'credit_status' => $reservation->credit_status,
                    'reservation_type' => $reservation->reservation_type,
                    // Dates
                    'contract_date' => $reservation->contract_date,
                    'confirmed_at' => $reservation->confirmed_at,
                    // Related data
                    'financing_tracker' => $reservation->financingTracker,
                    'title_transfer' => $reservation->titleTransfer,
                    'claim_file' => $reservation->claimFile,
                    'payment_installments' => $reservation->paymentInstallments,
                    // Flags
                    'is_cash_purchase' => $reservation->isCashPurchase(),
                    'is_bank_financing' => $reservation->isBankFinancing(),
                    'is_supported_bank' => $reservation->isSupportedBank(),
                    'requires_accounting_confirmation' => $reservation->requiresAccountingConfirmation(),
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

