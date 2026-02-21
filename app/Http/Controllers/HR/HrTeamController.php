<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\Team\TeamService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class HrTeamController extends Controller
{
    protected TeamService $teamService;

    public function __construct(TeamService $teamService)
    {
        $this->teamService = $teamService;
    }

    /**
     * List all teams with performance data.
     * GET /hr/teams
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);
            $perPage = ApiResponse::getPerPage($request);

            $teams = Team::with(['members', 'contracts'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $teamsData = $teams->getCollection()->map(function ($team) use ($year, $month) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'projects_count' => $team->contracts()->count(),
                    'locations' => $team->getProjectLocations(),
                    'members_count' => $team->members->count(),
                    'marketers' => $team->marketers()->get()->map(fn($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                    ]),
                    'avg_target_achievement' => $team->getAverageTargetAchievement($year, $month),
                    'created_at' => $team->created_at,
                ];
            });

            $teams->setCollection($teamsData);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة الفرق بنجاح',
                'data' => $teams->items(),
                'meta' => [
                    'pagination' => [
                        'total' => $teams->total(),
                        'count' => $teams->count(),
                        'per_page' => $teams->perPage(),
                        'current_page' => $teams->currentPage(),
                        'total_pages' => $teams->lastPage(),
                        'has_more_pages' => $teams->hasMorePages(),
                    ],
                    'period' => [
                        'year' => $year,
                        'month' => $month,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get team details with members.
     * GET /hr/teams/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            $team = Team::with(['members', 'contracts', 'creator'])->findOrFail($id);

            $membersWithPerformance = $team->members->map(function ($member) use ($year, $month) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'type' => $member->type,
                    'is_active' => $member->is_active,
                    'target_achievement_rate' => $member->getTargetAchievementRate($year, $month),
                    'deposits_count' => $member->getDepositsCount($year, $month),
                    'warnings_count' => $member->getWarningsCount($year),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الفريق بنجاح',
                'data' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'created_by' => $team->creator ? [
                        'id' => $team->creator->id,
                        'name' => $team->creator->name,
                    ] : null,
                    'projects_count' => $team->contracts()->count(),
                    'locations' => $team->getProjectLocations(),
                    'avg_target_achievement' => $team->getAverageTargetAchievement($year, $month),
                    'members' => $membersWithPerformance,
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Create a new team.
     * POST /hr/teams
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $team = $this->teamService->storeTeam($validated, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الفريق بنجاح',
                'data' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a team.
     * PUT /hr/teams/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $team = $this->teamService->updateTeam($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الفريق بنجاح',
                'data' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Delete a team.
     * DELETE /hr/teams/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->teamService->deleteTeam($id);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الفريق بنجاح',
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * List members of a team.
     * GET /hr/teams/{id}/members
     */
    public function members(int $id): JsonResponse
    {
        try {
            $team = Team::with(['members'])->findOrFail($id);
            $members = $team->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'phone' => $m->phone,
                'type' => $m->type,
                'is_active' => $m->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب أعضاء الفريق بنجاح',
                'data' => $members->values()->all(),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Assign a member to a team, or list members when no user_id is sent.
     * POST /hr/teams/{id}/members with body { "user_id": <id> } to assign.
     * POST /hr/teams/{id}/members with no body (or empty) returns the same as GET (list members) for frontend compatibility.
     */
    public function assignMember(Request $request, int $id): JsonResponse
    {
        if (!$request->filled('user_id')) {
            return $this->members($id);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $team = Team::findOrFail($id);
            $user = User::findOrFail($validated['user_id']);

            $user->update(['team_id' => $team->id]);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة العضو إلى الفريق بنجاح',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Remove a member from a team.
     * DELETE /hr/teams/{id}/members/{userId}
     */
    public function removeMember(int $id, int $userId): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            $user = User::where('id', $userId)->where('team_id', $id)->firstOrFail();

            $user->update(['team_id' => null]);

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة العضو من الفريق بنجاح',
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

