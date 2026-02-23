<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
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
        return response()->json([
            'success' => true,
            'data' => $this->planService->getPlanForDeveloper($contractId)
        ]);
    }

    public function store(\App\Http\Requests\Marketing\StoreDeveloperPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $plan = $this->planService->createOrUpdatePlan(
            $validated['contract_id'],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Developer marketing plan saved successfully',
            'data' => $plan
        ]);
    }
}
