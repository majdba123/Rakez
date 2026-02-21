<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\AssignTeamRequest;
use App\Services\Marketing\TeamManagementService;
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
use App\Models\Team;
<<<<<<< HEAD
<<<<<<< HEAD
use App\Http\Responses\ApiResponse;
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamManagementController extends Controller
{
    public function __construct(
        private TeamManagementService $teamService
    ) {}

<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
    public function index(Request $request): JsonResponse
=======
    public function index(): JsonResponse
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
=======
    public function index(): JsonResponse
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
    {
        $this->authorize('marketing.teams.view');

        $teams = Team::with(['members', 'creator'])->get();

        return response()->json([
            'success' => true,
            'data' => $teams
        ]);
    }

    public function assignCampaign(AssignCampaignRequest $request): JsonResponse
    {
        $team = Team::findOrFail($request->input('team_id'));
        $campaign = \App\Models\MarketingCampaign::findOrFail($request->input('campaign_id'));

        // Assign team members to the employee plan that owns the campaign
        $employeePlan = $campaign->employeePlan;
        if (!$employeePlan) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign does not have an associated employee plan'
            ], 404);
        }

        // Get team members
        $teamMembers = $team->members()->where('type', 'sales')->pluck('id')->toArray();

        if (empty($teamMembers)) {
            return response()->json([
                'success' => false,
                'message' => 'Team has no sales members to assign'
            ], 400);
        }

        // Assign team members to the marketing project team
        $marketingProject = $employeePlan->marketingProject;
        if ($marketingProject) {
            $this->teamService->assignTeamToProject($marketingProject->id, $teamMembers);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign assigned to team successfully',
            'data' => [
                'team_id' => $team->id,
                'campaign_id' => $campaign->id,
                'assigned_members' => $teamMembers
            ]
        ]);
    }

=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
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
