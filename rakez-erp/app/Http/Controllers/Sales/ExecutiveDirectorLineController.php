<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AssignExecutiveDirectorLineGroupMembersRequest;
use App\Http\Requests\Sales\AssignExecutiveDirectorLineTeamGroupsRequest;
use App\Http\Requests\Sales\AssignExecutiveDirectorLineTeamsRequest;
use App\Http\Requests\Sales\StoreExecutiveDirectorLineRequest;
use App\Http\Requests\Sales\UpdateExecutiveDirectorLineRequest;
use App\Http\Resources\Sales\ExecutiveDirectorLineResource;
use App\Models\ExecutiveDirectorLine;
use App\Models\TeamGroup;
use App\Models\TeamGroupLeader;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ExecutiveDirectorLineController extends Controller
{
    /**
     * List executive lines assigned to the current sales member (pivot executive_director_line_user).
     * GET /api/sales/member/executive-director-lines?per_page=&status=&line_type=
     */
    public function forSalesMember(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->type !== 'sales' || $user->is_manager) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الخاصية متاحة لموظف المبيعات فقط.',
            ], 403);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $hasProgressFields = Schema::hasColumns('executive_director_line_user', [
            'achieved_value',
            'member_status',
            'completed_at',
        ]);

        $query = ExecutiveDirectorLine::query()
            ->select([
                'executive_director_lines.id',
                'executive_director_lines.line_type',
                'executive_director_lines.value',
                'executive_director_lines.status',
                'executive_director_line_user.value_target',
                'executive_director_line_user.line_type_flag',
                'executive_director_line_user.created_at as assigned_at',
            ])
            ->join('executive_director_line_user', 'executive_director_line_user.executive_director_line_id', '=', 'executive_director_lines.id')
            ->where('executive_director_line_user.user_id', (int) $user->id)
            ->orderByDesc('id');

        if ($hasProgressFields) {
            $query->addSelect([
                'executive_director_line_user.achieved_value',
                'executive_director_line_user.member_status',
                'executive_director_line_user.completed_at',
            ]);
        } else {
            $query->selectRaw('NULL as achieved_value, ? as member_status, NULL as completed_at', ['in_progress']);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('line_type')) {
            $query->where('line_type', 'like', '%' . addcslashes((string) $request->input('line_type'), '%_\\') . '%');
        }

        $rows = $query->paginate($perPage);

        $data = collect($rows->items())
            ->map(function ($row) {
                return [
                    'line_id' => (int) $row->id,
                    'line_type' => $row->line_type,
                    'line_value' => $row->value !== null ? (float) $row->value : null,
                    'line_status' => (string) $row->status,
                    'target_value' => isset($row->value_target) ? (float) $row->value_target : null,
                    'achieved_value' => isset($row->achieved_value) ? (float) $row->achieved_value : null,
                    'remaining_value' => isset($row->value_target, $row->achieved_value)
                        ? max(0.0, round((float) $row->value_target - (float) $row->achieved_value, 2))
                        : null,
                    'member_status' => $row->member_status,
                    'line_type_flag' => $row->line_type_flag,
                    'completed_at' => $row->completed_at,
                    'assigned_at' => $row->assigned_at,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب أهدافك المعيّنة بنجاح.',
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
     * Executive director lines linked to the team the current user leads (pivot executive_director_line_team).
     * GET /api/sales/team/executive-director-lines?per_page=&status=&line_type=
     */
    public function forMyLedTeam(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSalesLeader()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الخاصية متاحة لقادة المبيعات فقط.',
            ], 403);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

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

        $teamId = (int) $user->team_id;

        $query = ExecutiveDirectorLine::query()
            ->with(['teams', 'teamGroups'])
            ->whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            })
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('line_type')) {
            $query->where('line_type', 'like', '%' . addcslashes((string) $request->input('line_type'), '%_\\') . '%');
        }

        $rows = $query->paginate($perPage);

        $data = collect($rows->items())
            ->map(fn ($row) => (new ExecutiveDirectorLineResource($row))->toArray($request))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب سطور المدير التنفيذي المرتبطة بفريقك.',
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
     * Link an executive line (already assigned to the leader’s team) to a single sub-group in array form.
     * POST /api/sales/team/executive-director-lines/{id}/team-groups — body: { "team_group_ids": [1] }
     */
    public function syncTeamGroupsForMyLedTeam(AssignExecutiveDirectorLineTeamGroupsRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSalesLeader() || ! $user->team_id) {
            return response()->json([
                'success' => false,
                'message' => 'هذه العملية لقائد مبيعات مرتبط بفريق فقط.',
            ], 403);
        }

        $teamId = (int) $user->team_id;

        $line = ExecutiveDirectorLine::query()
            ->whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            })
            ->find($id);

        if (! $line) {
            return response()->json([
                'success' => false,
                'message' => 'سطر المدير التنفيذي غير موجود أو غير مرتبط بفريقك.',
            ], 404);
        }

        $ids = array_values(array_unique(array_map('intval', $request->validated('team_group_ids'))));
        $line->teamGroups()->sync($ids);

        return response()->json([
            'success' => true,
            'message' => 'تم ربط سطر المدير التنفيذي بمجموعات الفريق (قادة المجموعات).',
            'data' => new ExecutiveDirectorLineResource($line->fresh()->load(['teams', 'teamGroups'])),
        ], 200);
    }

    /**
     * List executive lines the sales team leader assigned to this group. Optional team_group_id if user leads more than one group.
     * GET /api/sales/team-group/executive-director-lines?per_page=20&team_group_id=
     */
    public function forGroupLeader(Request $request): JsonResponse
    {
        $ctx = $this->groupLeaderContext($request);
        if ($ctx['error'] !== null) {
            return $ctx['error'];
        }
        $groupId = (int) $ctx['teamGroupId'];
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = ExecutiveDirectorLine::query()
            ->whereHas('teamGroups', function ($q) use ($groupId) {
                $q->where('team_groups.id', $groupId);
            })
            ->with([
                'teams',
                'teamGroups',
                'memberUsers' => function ($q) use ($groupId) {
                    $q->where('users.team_group_id', $groupId);
                },
            ])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('line_type')) {
            $query->where('line_type', 'like', '%' . addcslashes((string) $request->input('line_type'), '%_\\') . '%');
        }

        $rows = $query->paginate($perPage);

        $data = collect($rows->items())
            ->map(fn ($row) => (new ExecutiveDirectorLineResource($row))->toArray($request))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب سطور المدير التنفيذي لمجموعتك.',
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

    /**
     * Assign line to sales members in this group with per-member value_target (pivot executive_director_line_user).
     * Replaces current assignments in this group only.
     * POST /api/sales/team-group/executive-director-lines/{id}/members
     * body: { "members": [ { "user_id": 1, "value_target": 300 }, { "user_id": 2, "value_target": 200 } ], "team_group_id"?: 5 }
     */
    public function syncMembersForGroupLeader(AssignExecutiveDirectorLineGroupMembersRequest $request, int $id): JsonResponse
    {
        $ctx = $this->groupLeaderContext($request);
        if ($ctx['error'] !== null) {
            return $ctx['error'];
        }
        $groupId = (int) $ctx['teamGroupId'];

        $line = ExecutiveDirectorLine::query()
            ->whereHas('teamGroups', function ($q) use ($groupId) {
                $q->where('team_groups.id', $groupId);
            })
            ->find($id);

        if (! $line) {
            return response()->json([
                'success' => false,
                'message' => 'سطر المدير التنفيذي غير معيّن لمجموعتك.',
            ], 404);
        }

        $members = collect($request->validated('members'));
        $userIds = $members->pluck('user_id')->map(fn ($v) => (int) $v)->values()->all();
        if ($userIds === []) {
            $this->detachGroupMembersForLine($line, $groupId);
            $line->load(['teams', 'teamGroups', 'memberUsers']);

            return $this->groupLeaderMemberSyncResponse($line, $request, $groupId);
        }

        $lineValue = (float) ($line->value ?? 0);
        $totalAssignedValue = (float) $members->sum(fn ($member) => (float) $member['value_target']);

        $lineCents = (int) round($lineValue * 100);
        $assignedCents = (int) round($totalAssignedValue * 100);
        if ($assignedCents !== $lineCents) {
            return response()->json([
                'success' => false,
                'message' => 'مجموع value_target للأعضاء يجب أن يساوي قيمة السطر تماماً (لا أكثر ولا أقل).',
                'errors' => [
                    'members' => [
                        'مجموع value_target يساوي '.$totalAssignedValue.' بينما قيمة السطر '.$lineValue.'.',
                    ],
                ],
            ], 422);
        }

        $validCount = User::query()
            ->whereIn('id', $userIds)
            ->where('team_group_id', $groupId)
            ->where('type', 'sales')
            ->count();
        if ($validCount !== count($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'يُسمح بموظفي المبيعات (type=sales) التابعين لنفس المجموعة فقط.',
            ], 422);
        }

        $this->detachGroupMembersForLine($line, $groupId);
        $attachPayload = [];
        foreach ($members as $member) {
            $attachPayload[(int) $member['user_id']] = [
                'value_target' => round((float) $member['value_target'], 2),
                'line_type_flag' => $line->line_type,
            ];
        }
        $line->memberUsers()->attach($attachPayload);
        $line->load(['teams', 'teamGroups', 'memberUsers' => function ($q) use ($groupId) {
            $q->where('users.team_group_id', $groupId);
        }]);

        return $this->groupLeaderMemberSyncResponse($line, $request, $groupId);
    }

    /**
     * @return array{error: ?JsonResponse, teamGroupId: ?int}
     */
    protected function groupLeaderContext(Request $request): array
    {
        $user = $request->user();
        if ($user && ($user->isAdmin() || $user->hasRole('admin'))) {
            if (! $request->filled('team_group_id')) {
                return [
                    'error' => response()->json([
                        'success' => false,
                        'message' => 'للإدمن يجب إرسال team_group_id في الطلب.',
                    ], 422),
                    'teamGroupId' => null,
                ];
            }

            $groupId = (int) $request->input('team_group_id');
            $exists = TeamGroup::query()->whereKey($groupId)->exists();
            if (! $exists) {
                return [
                    'error' => response()->json([
                        'success' => false,
                        'message' => 'المجموعة غير موجودة.',
                    ], 404),
                    'teamGroupId' => null,
                ];
            }

            return [
                'error' => null,
                'teamGroupId' => $groupId,
            ];
        }

        $base = TeamGroupLeader::query()->where('user_id', $user->id);
        $count = (clone $base)->count();
        if ($count === 0) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'لست قائد مجموعة. لا يوجد سجل قائد لمجموعة.',
                ], 403),
                'teamGroupId' => null,
            ];
        }
        if ($count > 1 && ! $request->filled('team_group_id')) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'لديك أكثر من مجموعة كقائد. أرسل team_group_id في الطلب.',
                ], 422),
                'teamGroupId' => null,
            ];
        }
        $q = (clone $base);
        if ($request->filled('team_group_id')) {
            $q->where('team_group_id', (int) $request->input('team_group_id'));
        }
        $row = $q->first();
        if (! $row) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'المجموعة غير صالحة أو لست قائدها.',
                ], 404),
                'teamGroupId' => null,
            ];
        }

        return [
            'error' => null,
            'teamGroupId' => (int) $row->team_group_id,
        ];
    }

    private function detachGroupMembersForLine(ExecutiveDirectorLine $line, int $groupId): void
    {
        $old = User::query()
            ->where('team_group_id', $groupId)
            ->whereIn('id', function ($q) use ($line) {
                $q->select('user_id')
                    ->from('executive_director_line_user')
                    ->where('executive_director_line_id', $line->id);
            })
            ->pluck('id');
        if ($old->isNotEmpty()) {
            $line->memberUsers()->detach($old->all());
        }
    }

    private function groupLeaderMemberSyncResponse(ExecutiveDirectorLine $line, Request $request, int $groupId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث تعيين السطر لأعضاء المجموعة.',
            'data' => (new ExecutiveDirectorLineResource($line))->toArray($request),
            'meta' => [
                'team_group_id' => $groupId,
            ],
        ], 200);
    }

    /**
     * List ExecutiveDirectorLine rows (not sales targets).
     * Allowed for admin, or for sales employees with is_manager = true (no extra route middleware/permission).
     * GET /api/sales/executive/targets — query: from, to (created_at), status, line_type, per_page
     */
    public function executiveTargets(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowed = $user && ($user->isAdmin() || $user->hasRole('admin') || $user->isSalesTeamManager());
        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - الإدمن أو من نوع مبيعات ومدير (sales + is_manager) فقط.',
            ], 403);
        }

        try {
            $perPage = min((int) $request->query('per_page', 20), 100);

            $query = ExecutiveDirectorLine::query()
                ->with('teams')
                ->orderByDesc('id');

            if ($request->filled('team_id')) {
                $teamId = (int) $request->query('team_id');
                $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }
            if ($request->filled('line_type')) {
                $query->where('line_type', 'like', '%'.addcslashes((string) $request->query('line_type'), '%_\\').'%');
            }
            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->query('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->query('to'));
            }

            $rows = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ExecutiveDirectorLineResource::collection($rows->items()),
                'meta' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب السطور: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all lines (no sales target).
     * GET /api/sales/executive-director-lines
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $rows = ExecutiveDirectorLine::query()
            ->with('teams')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ExecutiveDirectorLineResource::collection($rows->items()),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/executive-director-lines
     * Body: line_type, value, status (status defaults to pending)
     */
    public function store(StoreExecutiveDirectorLineRequest $request): JsonResponse
    {
        $v = $request->validated();
        $row = ExecutiveDirectorLine::query()->create([
            'line_type' => $v['line_type'],
            'value' => $v['value'] ?? null,
            'status' => $v['status'] ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة السطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()->load('teams')),
        ], 201);
    }

    /**
     * Replace team assignment (single team in array). Admin or sales manager (sales + is_manager).
     * POST /api/sales/executive-director-lines/{id}/teams — body: { "team_ids": [1] }
     */
    public function syncTeams(AssignExecutiveDirectorLineTeamsRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $allowed = $user && ($user->isAdmin() || $user->hasRole('admin') || $user->isSalesTeamManager());
        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - الإدمن أو من نوع مبيعات ومدير (sales + is_manager) فقط.',
            ], 403);
        }


        $row = ExecutiveDirectorLine::query()->find($id);
        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'سطر المدير التنفيذي غير موجود.',
            ], 404);
        }

        $ids = array_values(array_unique(array_map('intval', $request->validated('team_ids'))));
        $row->teams()->sync($ids);

        return response()->json([
            'success' => true,
            'message' => 'تم ربط الفرق بالسطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()->load(['teams', 'teamGroups'])),
        ]);
    }

    /**
     * GET /api/sales/executive-director-lines/{id}
     */
    public function show(int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->with(['teams', 'teamGroups'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ExecutiveDirectorLineResource($row),
        ]);
    }

    /**
     * PUT /api/sales/executive-director-lines/{id}
     * Body: line_type, value, status (status optional; omit to keep current)
     */
    public function update(UpdateExecutiveDirectorLineRequest $request, int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->findOrFail($id);
        $v = $request->validated();
        $payload = [
            'line_type' => $v['line_type'],
            'value' => $v['value'] ?? null,
        ];
        if (array_key_exists('status', $v) && $v['status'] !== null) {
            $payload['status'] = $v['status'];
        }
        $row->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث السطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()->load('teams')),
        ]);
    }

    /**
     * DELETE /api/sales/executive-director-lines/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->findOrFail($id);
        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السطر.',
        ]);
    }
}
