<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingProjectController extends Controller
{
    public function __construct(
        private MarketingProjectService $projectService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->projectService->getProjectsWithCompletedContracts()
        ]);
    }

    public function show(int $contractId): JsonResponse
    {
        $details = $this->projectService->getProjectDetails($contractId);
        $durationStatus = $this->projectService->getContractDurationStatus($contractId);

        return response()->json([
            'success' => true,
            'data' => array_merge($details->toArray(), [
                'duration_status' => $durationStatus
            ])
        ]);
    }

    public function calculateBudget(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->projectService->calculateCampaignBudget(
                $request->input('contract_id'),
                $request->all()
            )
        ]);
    }
}
