<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Marketing\DeveloperMarketingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperMarketingPlanController extends Controller
{
    public function __construct(
        private DeveloperMarketingPlanService $planService
    ) {}

    public function show(int $contractId): JsonResponse
    {
        return ApiResponse::success($this->planService->getPlanForDeveloper($contractId));
    }

    public function store(\App\Http\Requests\Marketing\StoreDeveloperPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $plan = $this->planService->createOrUpdatePlan(
            $validated['contract_id'],
            $validated
        );

        return ApiResponse::success($plan, 'Developer marketing plan saved successfully');
    }
}
