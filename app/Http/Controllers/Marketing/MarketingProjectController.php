<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingProjectService;
use App\Http\Resources\Marketing\MarketingProjectResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingProjectController extends Controller
{
    public function __construct(
        private MarketingProjectService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = ApiResponse::getPerPage($request);
        $paginator = $this->projectService->getProjectsWithCompletedContracts($perPage);
        $data = MarketingProjectResource::collection($paginator->items())->resolve();

        return ApiResponse::success($data, 'تمت العملية بنجاح', 200, [
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function show(int $contractId): JsonResponse
    {
        $details = $this->projectService->getProjectDetails($contractId);
        $durationStatus = $this->projectService->getContractDurationStatus($contractId);
        $summaryFields = $this->projectService->getContractSummaryFields($details);

        return ApiResponse::success(array_merge($details->toArray(), $summaryFields, [
            'duration_status' => $durationStatus,
        ]));
    }

    public function calculateBudget(\App\Http\Requests\Marketing\CalculateBudgetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::success($this->projectService->calculateCampaignBudget(
            $validated['contract_id'],
            $validated
        ));
    }
}
