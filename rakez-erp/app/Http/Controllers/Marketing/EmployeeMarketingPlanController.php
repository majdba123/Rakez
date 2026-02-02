<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\EmployeeMarketingPlanService;
use App\Models\EmployeeMarketingPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeMarketingPlanController extends Controller
{
    public function __construct(
        private EmployeeMarketingPlanService $planService
    ) {}

    public function index(int $projectId): JsonResponse
    {
        $plans = EmployeeMarketingPlan::where('marketing_project_id', $projectId)->with('user')->get();
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    public function show(int $planId): JsonResponse
    {
        $plan = EmployeeMarketingPlan::with(['user', 'campaigns'])->findOrFail($planId);
        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }

    public function store(\App\Http\Requests\Marketing\StoreEmployeePlanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $plan = $this->planService->createPlan(
            $validated['marketing_project_id'],
            $validated['user_id'],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee marketing plan created successfully',
            'data' => $plan
        ]);
    }

    public function autoGenerate(Request $request): JsonResponse
    {
        $plan = $this->planService->autoGeneratePlan(
            $request->input('marketing_project_id'),
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee marketing plan auto-generated successfully',
            'data' => $plan
        ]);
    }
}
