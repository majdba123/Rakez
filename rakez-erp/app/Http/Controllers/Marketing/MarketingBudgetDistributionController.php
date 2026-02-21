<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreBudgetDistributionRequest;
use App\Services\Marketing\MarketingBudgetCalculationService;
use App\Models\MarketingBudgetDistribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingBudgetDistributionController extends Controller
{
    public function __construct(
        private MarketingBudgetCalculationService $calculationService
    ) {}

    /**
     * Store or update budget distribution
     *
     * @param StoreBudgetDistributionRequest $request
     * @return JsonResponse
     */
    public function store(StoreBudgetDistributionRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $projectId = $validated['marketing_project_id'];
            $planType = $validated['plan_type'];

            $distribution = $this->calculationService->saveOrUpdateDistribution(
                $projectId,
                $planType,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Budget distribution saved successfully',
                'data' => $distribution->load(['marketingProject', 'employeeMarketingPlan', 'developerMarketingPlan'])
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving budget distribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget distribution by project ID
     *
     * @param int $projectId
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $projectId, Request $request): JsonResponse
    {
        $planType = $request->query('plan_type');

        $query = MarketingBudgetDistribution::where('marketing_project_id', $projectId)
            ->with(['marketingProject', 'employeeMarketingPlan', 'developerMarketingPlan']);

        if ($planType) {
            $query->where('plan_type', $planType);
        }

        $distribution = $query->first();

        if (!$distribution) {
            return response()->json([
                'success' => false,
                'message' => 'Budget distribution not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $distribution
        ]);
    }

    /**
     * Recalculate budget distribution results
     *
     * @param int $distributionId
     * @return JsonResponse
     */
    public function recalculate(int $distributionId): JsonResponse
    {
        try {
            $distribution = MarketingBudgetDistribution::findOrFail($distributionId);

            $calculatedResults = $this->calculationService->calculateAll($distribution);
            $distribution->calculated_results = $calculatedResults;
            $distribution->save();

            return response()->json([
                'success' => true,
                'message' => 'Budget distribution recalculated successfully',
                'data' => [
                    'distribution' => $distribution->fresh(),
                    'results' => $calculatedResults
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while recalculating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calculated results for a budget distribution
     *
     * @param int $distributionId
     * @return JsonResponse
     */
    public function results(int $distributionId): JsonResponse
    {
        try {
            $distribution = MarketingBudgetDistribution::findOrFail($distributionId);

            // If results are not cached, calculate them
            if (!$distribution->calculated_results) {
                $calculatedResults = $this->calculationService->calculateAll($distribution);
                $distribution->calculated_results = $calculatedResults;
                $distribution->save();
            } else {
                $calculatedResults = $distribution->calculated_results;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'distribution_id' => $distribution->id,
                    'marketing_project_id' => $distribution->marketing_project_id,
                    'plan_type' => $distribution->plan_type,
                    'total_budget' => $distribution->total_budget,
                    'conversion_rate' => $distribution->conversion_rate,
                    'average_booking_value' => $distribution->average_booking_value,
                    'results' => $calculatedResults
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving results',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
