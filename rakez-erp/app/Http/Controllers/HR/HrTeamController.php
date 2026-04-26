<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\UserResource;
use App\Http\Requests\Team\AssignSalesTeamMemberRequest;
use App\Http\Requests\Team\AssignTeamSalesLeaderRequest;
use App\Http\Requests\Team\ListSalesLeadersRequest;
use App\Models\Team;
use App\Models\TeamGroup;
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
     * List members of a team.
     * GET /hr/teams/{id}/members — query: team_group_id أو group_id (اختياري) لتصفية المجموعة
     */
    public function members(Request $request, int $id): JsonResponse
    {
        try {
            $team = Team::query()->findOrFail($id);
            $teamGroupId = $request->filled('team_group_id')
                ? (int) $request->input('team_group_id')
                : ($request->filled('group_id') ? (int) $request->input('group_id') : null);
            if ($teamGroupId !== null && $teamGroupId < 1) {
                $teamGroupId = null;
            }
            if ($teamGroupId !== null) {
                $inTeam = TeamGroup::query()
                    ->where('id', $teamGroupId)
                    ->where('team_id', $id)
                    ->exists();
                if (! $inTeam) {
                    return response()->json([
                        'success' => false,
                        'message' => 'المجموعة غير موجودة أو لا تتبع هذا الفريق.',
                    ], 422);
                }
            }

            $membersQuery = $team->members();
            if ($teamGroupId !== null) {
                $membersQuery->where('team_group_id', $teamGroupId);
            }
            $members = $membersQuery->get();

            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            $membersData = $members->map(function ($member) use ($year, $month) {
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
                'message' => 'تم جلب أعضاء الفريق بنجاح',
                'data' => $membersData->values()->all(),
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
     * GET /hr/team-groups/{groupId}/members — list members in one team group (team comes from the group).
     */
    public function membersOfTeamGroup(Request $request, int $groupId): JsonResponse
    {
        $group = TeamGroup::query()->findOrFail($groupId);
        $request->merge(['team_group_id' => $groupId]);

        return $this->members($request, (int) $group->team_id);
    }

    /**
     * DELETE /hr/team-groups/{groupId}/members/{userId} — remove from group only, stays on team.
     */
    public function removeMemberFromTeamGroup(int $groupId, int $userId): JsonResponse
    {
        try {
            $group = TeamGroup::query()->findOrFail($groupId);
            $this->teamService->removeUserFromGroupOnly((int) $group->team_id, $groupId, $userId);
            $team = Team::query()->findOrFail((int) $group->team_id);

            return response()->json([
                'success' => true,
                'message' => 'تمت إزالة العضو من المجموعة مع بقائه ضمن الفريق.',
                'data' => [
                    'user_id' => $userId,
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                ],
            ], 200);
        } catch (Exception $e) {
            $code = 500;
            if (str_contains($e->getMessage(), 'الموظف غير ضمن')) {
                $code = 422;
            }
            if (str_contains($e->getMessage(), 'No query results')) {
                $code = 404;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
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
     * POST /hr/teams/{id}/sales-leader
     * One sales_leader (by user.type) per team; cannot assign a second while another is on the team.
     */
    public function assignSalesLeader(AssignTeamSalesLeaderRequest $request, int $id): JsonResponse
    {
        try {
            $v = $request->validated();
            $user = $this->teamService->assignSalesLeaderToTeam($id, (int) $v['user_id']);
            $user->loadMissing('team');

            return response()->json([
                'success' => true,
                'message' => 'تم تعيين قائد المبيعات للفريق بنجاح',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_type' => $user->type,
                    'team_id' => $user->team_id,
                    'team_name' => $user->team?->name,
                    'team_group_id' => $user->team_group_id,
                ],
            ], 200);
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'فقط المستخدمون') || str_contains($message, 'يوجد بالفعل')) {
                $code = 422;
            } elseif (str_contains($message, 'No query results')) {
                $code = 404;
            } else {
                $code = 500;
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $code);
        }
    }

    /**
     * GET /api/hr/teams/sales-leaders?team_id=&per_page=&search=
     */
    public function salesLeadersIndex(ListSalesLeadersRequest $request): JsonResponse
    {
        $v = $request->validated();
        $teamId = array_key_exists('team_id', $v) && $v['team_id'] !== null ? (int) $v['team_id'] : null;
        $perPage = (int) ($v['per_page'] ?? 15);
        $search = $v['search'] ?? null;
        if (is_string($search) && $search === '') {
            $search = null;
        }

        $paginator = $this->teamService->paginateSalesLeaders($teamId, $perPage, $search);

        $data = collect($paginator->items())->map(function ($user) {
            return array_merge(
                (new UserResource($user))->resolve(),
                [
                    'team_id' => $user->team_id,
                    'team' => $user->team ? [
                        'id' => $user->team->id,
                        'name' => $user->team->name,
                        'code' => $user->team->code,
                    ] : null,
                ]
            );
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب قادة المبيعات بنجاح',
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 200);
    }

    /**
     * DELETE /api/hr/teams/{id}/sales-leader/{userId}
     */
    public function removeSalesLeader(int $id, int $userId): JsonResponse
    {
        try {
            $this->teamService->removeSalesLeaderFromTeam($id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'تمت إزالة قائد المبيعات من الفريق بنجاح',
            ], 200);
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'ليس من نوع') || str_contains($message, 'ليس مرتبطاً')) {
                $code = 422;
            } elseif (str_contains($message, 'No query results') || str_contains($message, 'غير موجود')) {
                $code = 404;
            } else {
                $code = 500;
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $code);
        }
    }

    /**
     * Assign a member to a team (sales users only; same rules as project_management).
     * POST /hr/teams/{id}/members
     */
    public function assignMember(AssignSalesTeamMemberRequest $request, int $id): JsonResponse
    {
        try {
            $v = $request->validated();
            $user = $this->teamService->assignSalesMemberToTeamGroup(
                $id,
                (int) $v['team_group_id'],
                (int) $v['user_id']
            );
            $user->loadMissing('team');

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة عضو المبيعات إلى المجموعة داخل الفريق بنجاح',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_type' => $user->type,
                    'team_id' => $user->team_id,
                    'team_name' => $user->team?->name,
                    'team_group_id' => $user->team_group_id,
                ],
            ], 200);
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'يمكن إضافة')) {
                $status = 422;
            } elseif (str_contains($message, 'No query results')) {
                $status = 404;
            } else {
                $status = 500;
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
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

