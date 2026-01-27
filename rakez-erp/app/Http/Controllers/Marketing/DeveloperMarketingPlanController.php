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

    public function store(Request $request): JsonResponse
    {
        $plan = $this->planService->createOrUpdatePlan(
            $request->input('contract_id'),
            $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Developer marketing plan saved successfully',
            'data' => $plan
        ]);
    }
}
