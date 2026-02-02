<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\AssignTeamRequest;
use App\Services\Marketing\TeamManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamManagementController extends Controller
{
    public function __construct(
        private TeamManagementService $teamService
    ) {}

    public function assignTeam(int $projectId, AssignTeamRequest $request): JsonResponse
    {
        $assignments = $this->teamService->assignTeamToProject(
            $projectId,
            $request->input('user_ids')
        );

        return response()->json([
            'success' => true,
            'message' => 'Team assigned successfully',
            'data' => $assignments
        ]);
    }

    public function getTeam(int $projectId): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\MarketingProjectTeam::class);

        return response()->json([
            'success' => true,
            'data' => $this->teamService->getProjectTeam($projectId)
        ]);
    }

    public function recommendEmployee(int $projectId): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\MarketingProjectTeam::class);

        return response()->json([
            'success' => true,
            'data' => $this->teamService->recommendEmployeeForClient($projectId)
        ]);
    }
}
