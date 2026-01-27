<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\CalculateExpectedSalesRequest;
use App\Services\Marketing\ExpectedSalesService;
use App\Models\MarketingSetting;
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

    public function updateConversionRate(Request $request): JsonResponse
    {
        if ($request->user()->cannot('marketing.dashboard.view')) {
            abort(403, 'Unauthorized. Marketing permission required.');
        }

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
