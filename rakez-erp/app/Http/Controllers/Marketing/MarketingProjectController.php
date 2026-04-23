<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketing\MarketingProjectResource;
use App\Http\Responses\ApiResponse;
use App\Services\Marketing\MarketingProjectDetailAssembler;
use App\Services\Marketing\MarketingProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingProjectController extends Controller
{
    public function __construct(
        private MarketingProjectService $projectService,
        private MarketingProjectDetailAssembler $detailAssembler,
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

    public function show(int $id): JsonResponse
    {
        $details = $this->projectService->getProjectDetails($id);
        $durationStatus = $this->projectService->getContractDurationStatus($details->id);
        $responsibleSalesTeams = $this->projectService->buildResponsibleSalesTeams($details);
        $detailEnrichment = $this->projectService->enrichContractDetailForMarketingApi($details);

        return response()->json([
            'success' => true,
            'data' => $this->detailAssembler->assemble(
                $details,
                $durationStatus,
                $responsibleSalesTeams,
                $detailEnrichment
            ),
        ]);
    }
}
