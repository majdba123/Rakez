<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingDepositService;
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
     * Get pending deposits.
     * GET /api/accounting/deposits/pending
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'nullable|exists:contracts,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'payment_method' => 'nullable|string',
                'commission_source' => 'nullable|in:owner,buyer',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $filters = $request->only(['project_id', 'from_date', 'to_date', 'payment_method', 'commission_source', 'per_page']);
            $deposits = $this->depositService->getPendingDeposits($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العرابين المعلقة بنجاح',
                'data' => $deposits->items(),
                'meta' => [
                    'total' => $deposits->total(),
                    'per_page' => $deposits->perPage(),
                    'current_page' => $deposits->currentPage(),
                    'last_page' => $deposits->lastPage(),
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
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
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
                'data' => $reservations->items(),
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
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
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
