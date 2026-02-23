<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ApproveNegotiationRequest;
use App\Http\Requests\Sales\RejectNegotiationRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Sales\NegotiationApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class NegotiationApprovalController extends Controller
{
    protected NegotiationApprovalService $approvalService;

    public function __construct(NegotiationApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * List pending negotiation approvals.
     * GET /sales/negotiations/pending
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('sales.negotiation.approve')) {
            return ApiResponse::forbidden('Unauthorized');
        }

        $filters = $request->only(['contract_id', 'requested_by']);
        $filters['per_page'] = ApiResponse::getPerPage($request, 15, 100);
        $approvals = $this->approvalService->getPendingApprovals($filters);

        return ApiResponse::success($approvals->items(), 'تمت العملية بنجاح', 200, [
            'pagination' => ApiResponse::paginationMeta($approvals),
        ]);
    }

    /**
     * Approve a negotiation request.
     * POST /sales/negotiations/{id}/approve
     */
    public function approve(ApproveNegotiationRequest $request, int $id): JsonResponse
    {
        try {
            $approval = $this->approvalService->approve(
                $id,
                $request->user(),
                $request->input('notes')
            );

            return ApiResponse::success($approval, 'تمت الموافقة على طلب التفاوض بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Reject a negotiation request.
     * POST /sales/negotiations/{id}/reject
     */
    public function reject(RejectNegotiationRequest $request, int $id): JsonResponse
    {
        try {
            $approval = $this->approvalService->reject(
                $id,
                $request->user(),
                $request->input('reason')
            );

            return ApiResponse::success($approval, 'تم رفض طلب التفاوض');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}

