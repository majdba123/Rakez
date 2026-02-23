<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CommissionDistribution;
use App\Services\Accounting\AccountingCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class AccountingCommissionController extends Controller
{
    protected AccountingCommissionService $commissionService;

    public function __construct(AccountingCommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * List sold units with commission info.
     * GET /api/accounting/sold-units
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'nullable|exists:contracts,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'commission_source' => 'nullable|in:owner,buyer',
                'commission_status' => 'nullable|in:pending,approved,paid',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $filters = $request->only(['project_id', 'from_date', 'to_date', 'commission_source', 'commission_status', 'per_page']);
            $soldUnits = $this->commissionService->getSoldUnitsWithCommissions($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الوحدات المباعة بنجاح',
                'data' => $soldUnits->items(), // flattened list: project_name, unit_type, final_selling_price, commission_*, team_responsible at top level
                'meta' => [
                    'total' => $soldUnits->total(),
                    'per_page' => $soldUnits->perPage(),
                    'current_page' => $soldUnits->currentPage(),
                    'last_page' => $soldUnits->lastPage(),
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
     * List marketers/employees for commission distribution dropdown.
     * GET /api/accounting/marketers
     */
    public function marketers(Request $request): JsonResponse
    {
        try {
            $marketers = $this->commissionService->getCommissionEligibleMarketers();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة المسوقين بنجاح',
                'data' => $marketers,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single unit with full commission breakdown (same shape as form data inputs).
     * GET /api/accounting/sold-units/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $soldUnit = $this->commissionService->getSoldUnitWithCommission($id);
            $data = $this->commissionService->transformSoldUnitForDetail($soldUnit);
            $data['available_marketers'] = $this->commissionService->getCommissionEligibleMarketers();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات الوحدة بنجاح',
                'data' => $data,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Create manual commission.
     * POST /api/accounting/sold-units/{id}/commission
     */
    public function createManual(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'contract_unit_id' => 'required|exists:contract_units,id',
                'final_selling_price' => 'required|numeric|min:0',
                'commission_percentage' => 'required|numeric|min:0|max:100',
                'commission_source' => 'required|in:owner,buyer',
                'team_responsible' => 'nullable|string|max:255',
                'marketing_expenses' => 'nullable|numeric|min:0',
                'bank_fees' => 'nullable|numeric|min:0',
            ]);

            $data = $request->all();
            $data['sales_reservation_id'] = $id;

            $commission = $this->commissionService->createManualCommission($data);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العمولة بنجاح',
                'data' => $commission,
            ], 201);
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
            ], 400);
        }
    }

    /**
     * Update commission distributions.
     * PUT /api/accounting/commissions/{id}/distributions
     */
    public function updateDistributions(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'distributions' => 'required|array|min:1',
                'distributions.*.type' => 'required|in:lead_generation,persuasion,closing,team_leader,sales_manager,project_manager,external_marketer,other',
                'distributions.*.percentage' => 'required|numeric|min:0|max:100',
                'distributions.*.user_id' => 'nullable|exists:users,id',
                'distributions.*.external_name' => 'nullable|string|max:255',
                'distributions.*.bank_account' => 'nullable|string|max:255',
                'distributions.*.notes' => 'nullable|string',
            ]);

            $commission = $this->commissionService->updateCommissionDistributions(
                $id,
                $request->input('distributions')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث توزيعات العمولة بنجاح',
                'data' => $commission,
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
            ], 400);
        }
    }

    /**
     * Approve commission distribution.
     * POST /api/accounting/commissions/{id}/distributions/{distId}/approve
     */
    public function approveDistribution(Request $request, int $id, int $distId): JsonResponse
    {
        try {
            $distribution = $this->commissionService->approveDistribution(
                $distId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم الموافقة على توزيع العمولة بنجاح',
                'data' => $distribution,
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
            ], 400);
        }
    }

    /**
     * Reject commission distribution.
     * POST /api/accounting/commissions/{id}/distributions/{distId}/reject
     */
    public function rejectDistribution(Request $request, int $id, int $distId): JsonResponse
    {
        try {
            $request->validate([
                'notes' => 'nullable|string|max:1000',
            ]);

            $distribution = $this->commissionService->rejectDistribution(
                $distId,
                $request->user()->id,
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم رفض توزيع العمولة',
                'data' => $distribution,
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
            ], 400);
        }
    }

    /**
     * Get commission summary (Tab 4).
     * GET /api/accounting/commissions/{id}/summary
     */
    public function summary(Request $request, int $id): JsonResponse
    {
        try {
            $summary = $this->commissionService->getCommissionSummary($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب ملخص العمولة بنجاح',
                'data' => $summary,
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
     * Confirm commission payment.
     * POST /api/accounting/commissions/{id}/distributions/{distId}/confirm
     */
    public function confirmPayment(Request $request, int $id, int $distId): JsonResponse
    {
        try {
            $distribution = CommissionDistribution::findOrFail($distId);
            if ((int) $distribution->commission_id !== (int) $id) {
                return response()->json(['success' => false, 'message' => 'Distribution does not belong to this commission.'], 400);
            }
            $distribution = $this->commissionService->confirmCommissionPayment($distId);

            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد دفع العمولة بنجاح',
                'data' => $distribution,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
