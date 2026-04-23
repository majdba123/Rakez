<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accounting\AccountingDepositFollowUpResource;
use App\Http\Resources\Accounting\AccountingPendingDepositResource;
use App\Services\Accounting\AccountingDepositService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class AccountingDepositController extends Controller
{
    protected AccountingDepositService $depositService;

    public function __construct(AccountingDepositService $depositService)
    {
        $this->depositService = $depositService;
    }

    /**
     * Get pending deposits (unified accounting queue).
     * GET /api/accounting/deposits/pending?scope=all|actionable|closed&...
     * 
     * Supports query params:
     * - scope: all|actionable|closed (default: actionable)
     * - accounting_state: filter by accounting state
     * - deposit_status: filter by deposit status
     * - has_deposit: 0|1
     * - contract_id or project_id: filter by contract
     * - project_name: filter by project name (partial match)
     * - client_name: filter by client name (partial match)
     * - commission_source: owner|buyer
     * - from_date, to_date: date range
     * - payment_method: filter by payment method
     * - page, per_page: pagination
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'scope' => 'nullable|in:all,actionable,closed',
                'accounting_state' => 'nullable|string',
                'deposit_status' => 'nullable|string',
                'has_deposit' => 'nullable|in:0,1',
                'contract_id' => 'nullable|integer|exists:contracts,id',
                'project_id' => 'nullable|integer|exists:contracts,id',
                'project_name' => 'nullable|string',
                'client_name' => 'nullable|string',
                'commission_source' => 'nullable|in:owner,buyer',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'payment_method' => 'nullable|string',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $filters = $request->only([
                'scope', 'accounting_state', 'deposit_status', 'has_deposit',
                'contract_id', 'project_id', 'project_name', 'client_name',
                'commission_source', 'from_date', 'to_date', 'payment_method',
                'page', 'per_page'
            ]);

            // Service now handles ALL filtering (including post-filtering) before pagination
            $data = $this->depositService->getPendingDeposits($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة العربون الموحدة بنجاح',
                'data' => AccountingPendingDepositResource::collection(collect($data->items())),
                'meta' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm deposit receipt.
     * POST /api/accounting/deposits/{id}/confirm
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $deposit = $this->depositService->confirmDepositReceipt($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد استلام العربون بنجاح',
                'data' => $deposit,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit not found',
            ], 404);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'المعرف المُرسل يخص حجزاً')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get deposits for follow-up.
     * GET /api/accounting/deposits/follow-up
     */
    public function followUp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'nullable|exists:contracts,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'commission_source' => 'nullable|in:owner,buyer',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $filters = $request->only(['project_id', 'from_date', 'to_date', 'commission_source', 'per_page']);
            $reservations = $this->depositService->getDepositFollowUp($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات المتابعة بنجاح',
                'data' => AccountingDepositFollowUpResource::collection(collect($reservations->items())),
                'meta' => [
                    'total' => $reservations->total(),
                    'per_page' => $reservations->perPage(),
                    'current_page' => $reservations->currentPage(),
                    'last_page' => $reservations->lastPage(),
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deposit claim PDF data (مطالبة عربون). JSON only; frontend uses generateDepositClaimPdf(deposit).
     * GET /api/accounting/deposits/{id}/pdf-data or /api/deposit-claims/{id}/pdf-data
     */
    public function depositPdfData(int $id): JsonResponse
    {
        try {
            $deposit = $this->depositService->findDepositForAccountingAction($id);
            $deposit->load(['contract', 'contractUnit', 'confirmedBy']);

            $data = [
                'deposit_id' => $deposit->id,
                'reservation_id' => $deposit->sales_reservation_id,
                'id' => $deposit->id,
                'commission_source' => (string) ($deposit->commission_source ?? ''),
                'payment_method' => (string) ($deposit->payment_method ?? ''),
                'payment_date' => $deposit->payment_date?->format('Y-m-d') ?? '',
                'status' => (string) ($deposit->status ?? 'pending'),
                'notes' => (string) ($deposit->notes ?? ''),
                'contract' => [
                    'project_name' => (string) ($deposit->contract?->project_name ?? ''),
                ],
                'contractUnit' => [
                    'unit_type' => (string) ($deposit->contractUnit?->unit_type ?? ''),
                    'unit_number' => (string) ($deposit->contractUnit?->unit_number ?? ''),
                ],
                'confirmedBy' => [
                    'name' => (string) ($deposit->confirmedBy?->name ?? ''),
                ],
                'confirmed_at' => $deposit->confirmed_at?->toIso8601String() ?? null,
                'refund_reason' => null,
                'refunded_at' => $deposit->refunded_at?->toIso8601String() ?? null,
            ];

            return response()->json($data, 200, ['Content-Type' => 'application/json']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Deposit not found'], 404);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'المعرف المُرسل يخص حجزاً')) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process deposit refund.
     * POST /api/accounting/deposits/{id}/refund
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        try {
            $deposit = $this->depositService->processRefund($id);

            return response()->json([
                'success' => true,
                'message' => 'تم استرداد العربون بنجاح',
                'data' => $deposit,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit not found',
            ], 404);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'المعرف المُرسل يخص حجزاً')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate claim file.
     * POST /api/accounting/deposits/claim-file/{reservationId}
     */
    public function generateClaimFile(Request $request, int $reservationId): JsonResponse
    {
        try {
            $claimData = $this->depositService->generateClaimFile($reservationId);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء ملف المطالبة بنجاح',
                'data' => $claimData,
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
