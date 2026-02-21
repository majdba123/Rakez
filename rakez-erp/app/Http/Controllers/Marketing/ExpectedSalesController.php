<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\CalculateExpectedSalesRequest;
use App\Services\Marketing\ExpectedSalesService;
use App\Models\MarketingSetting;
<<<<<<< HEAD
<<<<<<< HEAD
use App\Models\ExpectedBooking;
use App\Http\Responses\ApiResponse;
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpectedSalesController extends Controller
{
    public function __construct(
        private ExpectedSalesService $salesService
    ) {}

    public function calculate(int $projectId, CalculateExpectedSalesRequest $request): JsonResponse
    {
        $expected = $this->salesService->createOrUpdateExpectedBookings($projectId, $request->validated());
        return response()->json([
            'success' => true,
            'data' => $expected
        ]);
    }

<<<<<<< HEAD
<<<<<<< HEAD
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|exists:marketing_projects,id',
            'direct_communications' => 'nullable|integer|min:0',
            'hand_raises' => 'nullable|integer|min:0',
            'conversion_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $projectId = $request->input('project_id');
        $data = $request->only(['direct_communications', 'hand_raises', 'conversion_rate']);
        
        $expected = $this->salesService->createOrUpdateExpectedBookings($projectId, $data);
        
        return response()->json([
            'success' => true,
            'message' => 'Expected sales created successfully',
            'data' => $expected
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = ExpectedBooking::with('marketingProject.contract');

        // Filter by project_id if provided
        if ($request->has('project_id')) {
            $query->where('marketing_project_id', $request->input('project_id'));
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $perPage = ApiResponse::getPerPage($request);
        $expectedSales = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::paginated($expectedSales, 'تم جلب قائمة المبيعات المتوقعة بنجاح');
    }

=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
    public function updateConversionRate(Request $request): JsonResponse
    {
        $this->authorize('update', new MarketingSetting());

        $request->validate(['value' => 'required|numeric|min:0|max:100']);

        $setting = MarketingSetting::updateOrCreate(
            ['key' => 'conversion_rate'],
            ['value' => $request->input('value'), 'description' => 'Default conversion rate for marketing']
        );

        return response()->json([
            'success' => true,
            'message' => 'Conversion rate updated successfully',
            'data' => $setting
        ]);
    }
}
