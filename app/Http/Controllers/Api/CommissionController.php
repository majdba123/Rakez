<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Services\Sales\CommissionService;
use App\Http\Requests\Commission\StoreCommissionRequest;
use App\Http\Requests\Commission\UpdateCommissionExpensesRequest;
use App\Http\Requests\Commission\DistributeCommissionRequest;
use App\Http\Responses\ApiResponse;
use App\Exceptions\CommissionException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CommissionController extends Controller
{
    protected CommissionService $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * Get commission list.
     *
     * @group Commissions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Commission::with(['contractUnit', 'salesReservation', 'distributions']);

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Scope by role: sales/sales_leader see only own or team commissions
            if (!$user->hasRole('admin') && !$user->hasRole('accountant')) {
                $allowedUserIds = $user->team
                    ? User::where('team', $user->team)->pluck('id')
                    : collect([$user->id]);
                $query->whereHas('salesReservation', fn ($q) => $q->whereIn('marketing_employee_id', $allowedUserIds));
            }

            $commissions = $query->paginate(ApiResponse::getPerPage($request, 15, 100));

            return ApiResponse::paginated($commissions, 'تم جلب قائمة العمولات بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Create a new commission.
     *
     * @group Commissions
     */
    public function store(StoreCommissionRequest $request): JsonResponse
    {
        try {
            $commission = $this->commissionService->createCommission(
                $request->input('contract_unit_id'),
                $request->input('sales_reservation_id'),
                $request->input('final_selling_price'),
                $request->input('commission_percentage'),
                $request->input('commission_source'),
                $request->input('team_responsible')
            );

            return ApiResponse::created(
                $commission->load(['contractUnit', 'salesReservation']),
                'تم إنشاء العمولة بنجاح'
            );
        } catch (CommissionException $e) {
            return $e->render();
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Get commission details.
     *
     * @group Commissions
     */
    public function show(Request $request, Commission $commission): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user->hasRole('admin') && !$user->hasRole('accountant')) {
                $allowedUserIds = $user->team
                    ? User::where('team', $user->team)->pluck('id')
                    : collect([$user->id]);
                $marketerId = $commission->salesReservation?->marketing_employee_id;
                if ($marketerId === null || !$allowedUserIds->contains($marketerId)) {
                    abort(403, 'Unauthorized to view this commission');
                }
            }

            $commission->load(['contractUnit', 'salesReservation', 'distributions.user']);

            return ApiResponse::success($commission, 'تم جلب تفاصيل العمولة بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Update commission expenses.
     *
     * @group Commissions
     */
    public function updateExpenses(UpdateCommissionExpensesRequest $request, Commission $commission): JsonResponse
    {
        try {
            $commission = $this->commissionService->updateExpenses(
                $commission,
                $request->input('marketing_expenses'),
                $request->input('bank_fees')
            );

            return ApiResponse::success($commission, 'تم تحديث مصاريف العمولة بنجاح');
        } catch (CommissionException $e) {
            return $e->render();
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Add distribution to commission.
     *
     * @group Commissions
     */
    public function addDistribution(Request $request, Commission $commission): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:lead_generation,persuasion,closing,team_leader,sales_manager,project_manager,external_marketer,other',
            'percentage' => 'required|numeric|min:0|max:100',
            'user_id' => 'nullable|exists:users,id',
            'external_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $distribution = $this->commissionService->addDistribution(
            $commission,
            $request->input('type'),
            $request->input('percentage'),
            $request->input('user_id'),
            $request->input('external_name'),
            $request->input('bank_account'),
            $request->input('notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Distribution added successfully.',
            'data' => $distribution->load('user'),
        ], 201);
    }

    /**
     * Distribute commission for lead generation.
     *
     * @group Commissions
     */
    public function distributeLeadGeneration(Request $request, Commission $commission): JsonResponse
    {
        $request->validate([
            'marketers' => 'required|array|min:1',
            'marketers.*.user_id' => 'required|exists:users,id',
            'marketers.*.percentage' => 'required|numeric|min:0|max:100',
            'marketers.*.bank_account' => 'nullable|string|max:255',
        ]);

        $distributions = $this->commissionService->distributeLeadGeneration(
            $commission,
            $request->input('marketers')
        );

        return response()->json([
            'success' => true,
            'message' => 'Lead generation distribution created successfully.',
            'data' => $distributions,
        ], 201);
    }

    /**
     * Distribute commission for persuasion.
     *
     * @group Commissions
     */
    public function distributePersuasion(Request $request, Commission $commission): JsonResponse
    {
        $request->validate([
            'employees' => 'required|array|min:1',
            'employees.*.user_id' => 'required|exists:users,id',
            'employees.*.percentage' => 'required|numeric|min:0|max:100',
            'employees.*.bank_account' => 'nullable|string|max:255',
        ]);

        $distributions = $this->commissionService->distributePersuasion(
            $commission,
            $request->input('employees')
        );

        return response()->json([
            'success' => true,
            'message' => 'Persuasion distribution created successfully.',
            'data' => $distributions,
        ], 201);
    }

    /**
     * Distribute commission for closing.
     *
     * @group Commissions
     */
    public function distributeClosing(Request $request, Commission $commission): JsonResponse
    {
        $request->validate([
            'closers' => 'required|array|min:1',
            'closers.*.user_id' => 'required|exists:users,id',
            'closers.*.percentage' => 'required|numeric|min:0|max:100',
            'closers.*.bank_account' => 'nullable|string|max:255',
        ]);

        $distributions = $this->commissionService->distributeClosing(
            $commission,
            $request->input('closers')
        );

        return response()->json([
            'success' => true,
            'message' => 'Closing distribution created successfully.',
            'data' => $distributions,
        ], 201);
    }

    /**
     * Distribute commission for management.
     *
     * @group Commissions
     */
    public function distributeManagement(Request $request, Commission $commission): JsonResponse
    {
        $request->validate([
            'management' => 'required|array|min:1',
            'management.*.type' => 'required|in:team_leader,sales_manager,project_manager,external_marketer,other',
            'management.*.percentage' => 'required|numeric|min:0|max:100',
            'management.*.user_id' => 'nullable|exists:users,id',
            'management.*.external_name' => 'nullable|string|max:255',
            'management.*.bank_account' => 'nullable|string|max:255',
        ]);

        $distributions = $this->commissionService->distributeManagement(
            $commission,
            $request->input('management')
        );

        return response()->json([
            'success' => true,
            'message' => 'Management distribution created successfully.',
            'data' => $distributions,
        ], 201);
    }

    /**
     * Approve a commission distribution.
     *
     * @group Commissions
     */
    public function approveDistribution(CommissionDistribution $distribution): JsonResponse
    {
        Gate::authorize('approve-commission-distribution');

        $distribution = $this->commissionService->approveDistribution(
            $distribution,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Distribution approved successfully.',
            'data' => $distribution,
        ]);
    }

    /**
     * Reject a commission distribution.
     *
     * @group Commissions
     */
    public function rejectDistribution(Request $request, CommissionDistribution $distribution): JsonResponse
    {
        Gate::authorize('approve-commission-distribution');

        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $distribution = $this->commissionService->rejectDistribution(
            $distribution,
            auth()->id(),
            $request->input('notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Distribution rejected successfully.',
            'data' => $distribution,
        ]);
    }

    /**
     * Approve entire commission.
     *
     * @group Commissions
     */
    public function approve(Commission $commission): JsonResponse
    {
        Gate::authorize('approve-commission');

        try {
            $commission = $this->commissionService->approveCommission($commission);

            return response()->json([
                'success' => true,
                'message' => 'Commission approved successfully.',
                'data' => $commission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mark commission as paid.
     *
     * @group Commissions
     */
    public function markAsPaid(Commission $commission): JsonResponse
    {
        Gate::authorize('mark-commission-paid');

        try {
            $commission = $this->commissionService->markCommissionAsPaid($commission);

            return response()->json([
                'success' => true,
                'message' => 'Commission marked as paid successfully.',
                'data' => $commission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get commission summary.
     *
     * @group Commissions
     */
    public function summary(Commission $commission): JsonResponse
    {
        $summary = $this->commissionService->getCommissionSummary($commission);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Update distribution percentage.
     *
     * @group Commissions
     */
    public function updateDistribution(Request $request, CommissionDistribution $distribution): JsonResponse
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $distribution = $this->commissionService->updateDistributionPercentage(
                $distribution,
                $request->input('percentage')
            );

            return response()->json([
                'success' => true,
                'message' => 'Distribution updated successfully.',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a distribution.
     *
     * @group Commissions
     */
    public function deleteDistribution(CommissionDistribution $distribution): JsonResponse
    {
        try {
            $this->commissionService->deleteDistribution($distribution);

            return response()->json([
                'success' => true,
                'message' => 'Distribution deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
