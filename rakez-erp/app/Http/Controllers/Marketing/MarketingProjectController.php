<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingProjectService;
use App\Http\Resources\Marketing\MarketingProjectResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingProjectController extends Controller
{
    public function __construct(
        private MarketingProjectService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = ApiResponse::getPerPage($request);
        $filters = [
            'q' => $request->query('q'),
            'city' => $request->query('city'),
            'district' => $request->query('district'),
            'status' => $request->query('status'),
        ];

        $paginator = $this->projectService->getProjects($filters, $perPage);
        $data = MarketingProjectResource::collection($paginator->items())->resolve();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ],
        ], 200);
    }

    public function show(int $contractId): JsonResponse
    {
        $details = $this->projectService->getProjectDetails($contractId);
        $durationStatus = $this->projectService->getContractDurationStatus($contractId);
        $responsibleSalesTeams = $this->projectService->buildResponsibleSalesTeams($details);
        $detailEnrichment = $this->projectService->enrichContractDetailForMarketingApi($details);
        
        // Get canonical shared metrics
        $canonicalMetrics = $this->projectService->getCanonicalMetrics($details);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $canonicalMetrics,
                $details->toArray(), 
                $detailEnrichment, 
                [
                    'duration_status' => $durationStatus,
                    'responsible_sales_teams' => $responsibleSalesTeams,
                    /** Canonical contract/pricing source — no campaign budget math (use POST …/developer-plans/calculate-budget). */
                    'pricing_source' => $this->projectService->buildPricingSourceForContract($details),
                ]
            ),
        ]);
    }
}
