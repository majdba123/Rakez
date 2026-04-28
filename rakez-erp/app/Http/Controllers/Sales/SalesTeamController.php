<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\RateTeamMemberRequest;
use App\Http\Resources\TeamGroupLeaderResource;
use App\Http\Resources\TeamGroupResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\TeamGroup;
use App\Models\TeamGroupLeader;
use App\Models\User;
use App\Services\Sales\SalesTeamService;
use App\Services\Team\TeamGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesTeamController extends Controller
{
    public function __construct(
        private SalesTeamService $teamService,
        private TeamGroupService $teamGroupService
    ) {}

    /**
     * تقييم عضو الفريق من 1 إلى 5 نجوم.
     * PATCH /api/sales/team/members/{memberId}/rating
     */
    public function rateMember(RateTeamMemberRequest $request, int $memberId): JsonResponse
    {
        try {
            $rating = $request->has('rating') ? (int) $request->input('rating') : null;
            $comment = $request->input('comment');
            if ($comment !== null) {
                $comment = trim($comment);
                $comment = $comment === '' ? null : $comment;
            }
            $data = $this->teamService->rateMember(
                $request->user(),
                $memberId,
                $rating,
                $comment
            );
            $msg = $data->comment && $data->rating !== null ? 'تم حفظ التعليق والتقييم بنجاح'
                : ($data->comment ? 'تم حفظ التعليق عن الموظف بنجاح' : 'تم حفظ التقييم بنجاح');
            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => [
                    'member_id' => $data->member_id,
                    'rating' => $data->rating,
                    'comment' => $data->comment,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * إخراج عضو من الفريق (إلغاء انتمائه للفريق).
     * POST /api/sales/team/members/{memberId}/remove
     */
    public function removeMember(Request $request, int $memberId): JsonResponse
    {
        try {
            $this->teamService->removeMemberFromTeam($request->user(), $memberId);
            return response()->json([
                'success' => true,
                'message' => 'تم إخراج العضو من الفريق بنجاح',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * ترشيح أعضاء الفريق بالذكاء الاصطناعي.
     * GET /api/sales/team/recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            $items = $this->teamService->getRecommendations($request->user());
            $data = $items->map(fn (array $item) => $this->teamService->memberToApiShape($item, true))->values();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Team the current user leads (sales_leader / sales manager on users.team_id).
     * GET /api/sales/team/led
     */
    public function myLedTeam(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSalesLeader()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الخاصية متاحة لقادة المبيعات فقط.',
            ], 403);
        }

        if (! $user->team_id) {
            return response()->json([
                'success' => true,
                'message' => 'لا يوجد فريق معيّن لك حالياً.',
                'data' => null,
            ], 200);
        }

        $team = Team::query()->find($user->team_id);

        if (! $team) {
            return response()->json([
                'success' => true,
                'message' => 'الفريق المرتبط بحسابك غير متوفر.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب فريقك بنجاح',
            'data' => (new TeamResource($team))->resolve(),
        ], 200);
    }

    /**
     * All sub-groups (team_groups) for the team the current user leads.
     * GET /api/sales/team/groups?per_page=15
     */
    public function myTeamGroups(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSalesLeader()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الخاصية متاحة لقادة المبيعات فقط.',
            ], 403);
        }

        $perPage = (int) min(100, max(1, (int) $request->input('per_page', 15)));

        if (! $user->team_id) {
            return response()->json([
                'success' => true,
                'message' => 'لا يوجد فريق معيّن لك حالياً.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ], 200);
        }

        $rows = $this->teamGroupService->paginate((int) $user->team_id, $perPage);

        $data = collect($rows->items())
            ->map(fn ($g) => (new TeamGroupResource($g))->toArray($request))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب مجموعات فريقك بنجاح',
            'data' => $data,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ], 200);
    }

    /**
     * All sub-group leader assignments (team_group_leaders) for groups under the team the user leads.
     * GET /api/sales/team/group-leaders?per_page=15
     */
    public function myTeamGroupLeaders(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSalesLeader()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الخاصية متاحة لقادة المبيعات فقط.',
            ], 403);
        }

        $perPage = (int) min(100, max(1, (int) $request->input('per_page', 15)));

        if (! $user->team_id) {
            return response()->json([
                'success' => true,
                'message' => 'لا يوجد فريق معيّن لك حالياً.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ], 200);
        }

        $rows = $this->teamGroupService->paginateGroupLeadersForTeam((int) $user->team_id, $perPage);

        $data = collect($rows->items())
            ->map(fn ($row) => (new TeamGroupLeaderResource($row))->toArray($request))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب قادة مجموعات فريقك بنجاح',
            'data' => $data,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ], 200);
    }

    /**
     * Team(s) that the current user leads as a team-group leader.
     * If the user leads multiple groups across multiple teams, returns an array.
     * GET /api/sales/team-group/led-team
     */
    public function myLedTeamAsGroupLeader(Request $request): JsonResponse
    {
        $user = $request->user();

        $groupIds = TeamGroupLeader::query()
            ->where('user_id', $user->id)
            ->pluck('team_group_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($groupIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لست قائد مجموعة حالياً.',
            ], 403);
        }

        $teams = Team::query()
            ->whereIn('id', TeamGroup::query()->whereIn('id', $groupIds)->pluck('team_id'))
            ->orderBy('name')
            ->get();

        $data = $teams->map(fn ($t) => (new TeamResource($t))->resolve())->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب فريقك بنجاح',
            'data' => count($data) === 1 ? $data[0] : $data,
        ], 200);
    }

    /**
     * List team-groups that the current user leads.
     * GET /api/sales/team-group/led-groups
     */
    public function myLedGroups(Request $request): JsonResponse
    {
        $user = $request->user();

        $groupIds = TeamGroupLeader::query()
            ->where('user_id', $user->id)
            ->pluck('team_group_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($groupIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لست قائد مجموعة حالياً.',
            ], 403);
        }

        $groups = TeamGroup::query()
            ->whereIn('id', $groupIds->all())
            ->with(['team', 'teamGroupLeader.user'])
            ->orderBy('name')
            ->get();

        $data = $groups->map(fn ($g) => (new TeamGroupResource($g))->toArray($request))->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب مجموعاتك بنجاح',
            'data' => $data,
        ], 200);
    }

    /**
     * List members of the team-group(s) the current user leads.
     * GET /api/sales/team-group/members?per_page=20&team_group_id=
     */
    public function myGroupMembers(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) min(100, max(1, (int) $request->input('per_page', 20)));

        $base = TeamGroupLeader::query()->where('user_id', $user->id);
        $count = (clone $base)->count();
        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'لست قائد مجموعة حالياً.',
            ], 403);
        }

        if ($count > 1 && ! $request->filled('team_group_id')) {
            return response()->json([
                'success' => false,
                'message' => 'لديك أكثر من مجموعة كقائد. أرسل team_group_id في الطلب.',
            ], 422);
        }

        $leaderRow = (clone $base)
            ->when($request->filled('team_group_id'), fn ($q) => $q->where('team_group_id', (int) $request->input('team_group_id')))
            ->first();
        if (! $leaderRow) {
            return response()->json([
                'success' => false,
                'message' => 'المجموعة غير صالحة أو لست قائدها.',
            ], 404);
        }

        $groupId = (int) $leaderRow->team_group_id;
        $rows = User::query()
            ->where('team_group_id', $groupId)
            ->orderBy('name')
            ->paginate($perPage);

        $data = collect($rows->items())->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'type' => $member->type,
                'team_id' => $member->team_id,
                'team_group_id' => $member->team_group_id,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب أعضاء مجموعتك بنجاح',
            'data' => $data,
            'meta' => [
                'team_group_id' => $groupId,
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ], 200);
    }
}
