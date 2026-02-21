<?php

namespace App\Http\Controllers\ProjectManagement;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Resources\ProjectManagement\ProjectListCardResource;
use App\Services\ProjectManagement\ProjectManagementProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectManagementProjectController extends Controller
{
    public function __construct(
        private ProjectManagementProjectService $projectService
    ) {}

    /**
     * List projects by segment with counts. For project management UI tabs.
     * GET /api/project_management/projects?segment=unready|ready_for_marketing|archive&team_id=&search=&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $segment = $request->query('segment', ProjectManagementProjectService::SEGMENT_UNREADY);
        if (! in_array($segment, [
            ProjectManagementProjectService::SEGMENT_UNREADY,
            ProjectManagementProjectService::SEGMENT_READY,
            ProjectManagementProjectService::SEGMENT_ARCHIVE,
        ], true)) {
            $segment = ProjectManagementProjectService::SEGMENT_UNREADY;
        }

        $perPage = ApiResponse::getPerPage($request, 15, 100);
        $teamId = $request->query('team_id');
        $search = $request->query('search');

        $counts = $this->projectService->getSegmentCounts($teamId, $search);
        $paginator = $this->projectService->getProjectsBySegment($segment, $perPage, $teamId, $search);

        $data = ProjectListCardResource::collection($paginator->items())->resolve();
        return ApiResponse::success($data, 'تمت العملية بنجاح', 200, [
            'segment_counts' => $counts,
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
