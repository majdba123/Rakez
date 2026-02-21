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
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
        $query = EmployeeMarketingPlan::with('user');

        // Support both route parameter and query parameter for backward compatibility
        if ($projectId) {
            $query->where('marketing_project_id', $projectId);
        } elseif ($request->has('project_id')) {
            $query->where('marketing_project_id', $request->input('project_id'));
        }

        // Filter by user_id if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

<<<<<<< HEAD
        $perPage = ApiResponse::getPerPage($request);
        $plans = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::paginated($plans, 'تم جلب قائمة خطط التسويق بنجاح');
=======
=======
>>>>>>> parent of 29c197a (Add edits)
        $plans = EmployeeMarketingPlan::where('marketing_project_id', $projectId)->with('user')->get();
=======
        $plans = $query->get();
        
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
=======
        $plans = EmployeeMarketingPlan::where('marketing_project_id', $projectId)->with('user')->get();
>>>>>>> parent of 29c197a (Add edits)
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
<<<<<<< HEAD
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
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
