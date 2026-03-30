<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketing\EmployeeMarketingPlanResource;
use App\Http\Responses\ApiResponse;
use App\Models\EmployeeMarketingPlan;
use App\Services\Marketing\EmployeeMarketingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeMarketingPlanController extends Controller
{
    public function __construct(
        private EmployeeMarketingPlanService $planService
    ) {}

    public function index(Request $request, ?int $projectId = null): JsonResponse
    {
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

        $perPage = ApiResponse::getPerPage($request);
        $plans = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success(
            EmployeeMarketingPlanResource::collection($plans->items())->resolve(),
            'تم جلب قائمة خطط التسويق بنجاح',
            200,
            [
                'pagination' => [
                    'total' => $plans->total(),
                    'count' => $plans->count(),
                    'per_page' => $plans->perPage(),
                    'current_page' => $plans->currentPage(),
                    'total_pages' => $plans->lastPage(),
                    'has_more_pages' => $plans->hasMorePages(),
                ],
            ]
        );
    }

    /**
     * Employee plans PDF data (unified report shape). JSON only; frontend uses buildDocumentPdf(payload).
     * GET /api/marketing/employee-plans/pdf-data?marketing_project_id=
     */
    public function pdfData(Request $request): JsonResponse
    {
        $projectId = $request->input('marketing_project_id');
        if (!$projectId) {
            return response()->json([
                'success' => false,
                'message' => 'marketing_project_id is required',
            ], 422);
        }
        $plans = EmployeeMarketingPlan::with('user')
            ->where('marketing_project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->get();
        $rows = $plans->map(fn ($p) => [
            (string) ($p->user?->name ?? ''),
            (string) ($p->marketing_value ?? ''),
            (string) ($p->marketing_percent ?? ''),
            (string) ($p->commission_value ?? ''),
        ])->all();
        $payload = [
            'title' => 'خطط تسويق الموظفين',
            'subtitle' => '',
            'sections' => [
                [
                    'sectionTitle' => 'قائمة الخطط',
                    'headers' => ['الموظف', 'قيمة التسويق', 'نسبة التسويق %', 'قيمة العمولة'],
                    'rows' => $rows,
                ],
            ],
            'footer' => '',
        ];
        return response()->json($payload, 200, ['Content-Type' => 'application/json']);
    }

    public function show(int $planId): JsonResponse
    {
        $plan = EmployeeMarketingPlan::with(['user', 'campaigns'])->findOrFail($planId);

        return ApiResponse::success(
            (new EmployeeMarketingPlanResource($plan))->resolve()
        );
    }

    public function store(\App\Http\Requests\Marketing\StoreEmployeePlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $userId = $validated['user_id'] ?? auth()->id();

        $plan = $this->planService->createPlan(
            $validated['marketing_project_id'],
            $userId,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء خطة التسويق للموظف بنجاح',
            'data' => $plan
        ]);
    }

    public function autoGenerate(Request $request): JsonResponse
    {
        $userId = $request->input('user_id') ?? auth()->id();

        $plan = $this->planService->autoGeneratePlan(
            $request->input('marketing_project_id'),
            $userId,
            $request->input('marketing_percent'),
            $request->input('strategy', 'ai')
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء خطة التسويق للموظف تلقائياً بنجاح',
            'data' => $plan
        ]);
    }

    public function suggest(Request $request, \App\Services\Marketing\MarketingPlanSuggestionService $suggestionService): JsonResponse
    {
        $inputs = $request->all();
        $suggestion = $suggestionService->suggest($inputs);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء اقتراحات خطة التسويق بنجاح',
            'data' => $suggestion
        ]);
    }
}
