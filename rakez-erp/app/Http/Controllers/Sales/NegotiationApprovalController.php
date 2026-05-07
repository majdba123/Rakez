<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ApproveNegotiationRequest;
use App\Http\Requests\Sales\RejectNegotiationRequest;
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
        // Check permission
        if (!$request->user()->can('sales.negotiation.approve')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $filters = $request->only(['contract_id', 'requested_by', 'per_page']);
        $approvals = $this->approvalService->getPendingApprovals($filters);

        return response()->json([
            'success' => true,
            'data' => $approvals->items(),
            'meta' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ],
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

            return response()->json([
                'success' => true,
                'message' => 'تمت الموافقة على طلب التفاوض بنجاح',
                'data' => $approval,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
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

            return response()->json([
                'success' => true,
                'message' => 'تم رفض طلب التفاوض',
                'data' => $approval,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

