<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\Sales\DepositService;
use App\Http\Requests\Deposit\StoreDepositRequest;
use App\Http\Requests\Deposit\UpdateDepositRequest;
use App\Http\Responses\ApiResponse;
use App\Exceptions\DepositException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DepositController extends Controller
{
    protected DepositService $depositService;

    public function __construct(DepositService $depositService)
    {
        $this->depositService = $depositService;
    }

    /**
     * Get deposits list for management.
     *
     * @group Deposits
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'nullable|in:pending,received,refunded,confirmed',
                'from' => 'nullable|date',
                'to' => 'nullable|date|after_or_equal:from',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $deposits = $this->depositService->getDepositsForManagement(
                $request->input('status'),
                $request->input('from'),
                $request->input('to'),
                ApiResponse::getPerPage($request, 15, 100),
                $request->user()
            );

            return ApiResponse::paginated($deposits, 'تم جلب قائمة الودائع بنجاح');
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Create a new deposit.
     *
     * @group Deposits
     */
    public function store(StoreDepositRequest $request): JsonResponse
    {
        try {
            $deposit = $this->depositService->createDeposit(
                $request->input('sales_reservation_id'),
                $request->input('contract_id'),
                $request->input('contract_unit_id'),
                $request->input('amount'),
                $request->input('payment_method'),
                $request->input('client_name'),
                $request->input('payment_date'),
                $request->input('commission_source'),
                $request->input('notes')
            );

            return ApiResponse::created(
                $deposit->load(['salesReservation', 'contract', 'contractUnit']),
                'تم إنشاء الوديعة بنجاح'
            );
        } catch (DepositException $e) {
            return $e->render();
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Get deposit details.
     *
     * @group Deposits
     */
    public function show(Request $request, int $depositId): JsonResponse
    {
        try {
            $details = $this->depositService->getDepositDetails($depositId, $request->user());

            return response()->json([
                'success' => true,
                'data' => $details,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Update deposit information.
     *
     * @group Deposits
     */
    public function update(Request $request, Deposit $deposit): JsonResponse
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:bank_transfer,cash,bank_financing',
            'client_name' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $deposit = $this->depositService->updateDeposit(
                $deposit,
                $request->only(['amount', 'payment_method', 'client_name', 'payment_date', 'notes'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Deposit updated successfully.',
                'data' => $deposit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm receipt of deposit.
     *
     * @group Deposits
     */
    public function confirmReceipt(Deposit $deposit): JsonResponse
    {
        Gate::authorize('confirm-deposit-receipt');

        try {
            $deposit = $this->depositService->confirmReceipt($deposit, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Deposit receipt confirmed successfully.',
                'data' => $deposit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mark deposit as received.
     *
     * @group Deposits
     */
    public function markAsReceived(Deposit $deposit): JsonResponse
    {
        try {
            $deposit = $this->depositService->markAsReceived($deposit);

            return response()->json([
                'success' => true,
                'message' => 'Deposit marked as received successfully.',
                'data' => $deposit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Refund a deposit.
     *
     * @group Deposits
     */
    public function refund(Deposit $deposit): JsonResponse
    {
        Gate::authorize('refund-deposit');

        try {
            $deposit = $this->depositService->refundDeposit($deposit);

            return response()->json([
                'success' => true,
                'message' => 'Deposit refunded successfully.',
                'data' => $deposit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get deposits for follow-up.
     *
     * @group Deposits
     */
    public function followUp(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $deposits = $this->depositService->getDepositsForFollowUp(
            $request->input('from'),
            $request->input('to'),
            ApiResponse::getPerPage($request, 15, 100)
        );

        return response()->json([
            'success' => true,
            'data' => $deposits,
        ]);
    }

    /**
     * Generate commission claim file for deposit.
     *
     * @group Deposits
     */
    public function generateClaimFile(Deposit $deposit): JsonResponse
    {
        try {
            $path = $this->depositService->generateClaimFile($deposit);

            return response()->json([
                'success' => true,
                'message' => 'Claim file generated successfully.',
                'data' => [
                    'claim_file_path' => $path,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get deposit statistics by project.
     *
     * @group Deposits
     */
    public function statsByProject(int $contractId): JsonResponse
    {
        $stats = $this->depositService->getDepositStatsByProject($contractId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get deposits by reservation.
     *
     * @group Deposits
     */
    public function byReservation(int $salesReservationId): JsonResponse
    {
        $deposits = $this->depositService->getDepositsByReservation($salesReservationId);

        return response()->json([
            'success' => true,
            'data' => $deposits,
        ]);
    }

    /**
     * Check if deposit can be refunded.
     *
     * @group Deposits
     */
    public function canRefund(Deposit $deposit): JsonResponse
    {
        $result = $this->depositService->canRefund($deposit);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get refundable deposits for a project.
     *
     * @group Deposits
     */
    public function refundableDeposits(int $contractId): JsonResponse
    {
        $deposits = $this->depositService->getRefundableDeposits($contractId);

        return response()->json([
            'success' => true,
            'data' => $deposits,
        ]);
    }

    /**
     * Bulk confirm deposits.
     *
     * @group Deposits
     */
    public function bulkConfirm(Request $request): JsonResponse
    {
        Gate::authorize('confirm-deposit-receipt');

        $request->validate([
            'deposit_ids' => 'required|array|min:1',
            'deposit_ids.*' => 'required|exists:deposits,id',
        ]);

        $result = $this->depositService->bulkConfirmDeposits(
            $request->input('deposit_ids'),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk confirmation completed.',
            'data' => $result,
        ]);
    }

    /**
     * Delete a deposit.
     *
     * @group Deposits
     */
    public function destroy(Deposit $deposit): JsonResponse
    {
        try {
            $this->depositService->deleteDeposit($deposit);

            return response()->json([
                'success' => true,
                'message' => 'Deposit deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
