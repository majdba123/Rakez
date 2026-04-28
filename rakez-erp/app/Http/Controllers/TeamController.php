<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithCsvImportUpload;
use App\Http\Requests\Team\AssignSalesTeamMemberRequest;
use App\Http\Requests\Team\AssignTeamSalesLeaderRequest;
use App\Http\Requests\Team\ListSalesLeadersRequest;
use App\Http\Requests\Team\ImportTeamsCsv;
use App\Http\Requests\Team\TeamContractsRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\Contract\ContractIndexResource;
use App\Http\Resources\Shared\UserResource;
use App\Http\Resources\TeamResource;
use App\Jobs\ProcessTeamsCsv;
use App\Models\CsvImport;
use App\Models\Team;
use App\Models\TeamGroup;
use App\Services\Contract\ContractService;
use App\Services\Team\TeamService;
use App\Support\TabularImportReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Exception;

class TeamController extends Controller
{
    use RespondsWithCsvImportUpload;

    protected TeamService $teamService;
    protected ContractService $contractService;

    public function __construct(TeamService $teamService, ContractService $contractService)
    {
        $this->teamService = $teamService;
        $this->contractService = $contractService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'sort_by' => $request->input('sort_by', 'created_at'),
                'sort_order' => $request->input('sort_order', 'desc'),
            ];

            $perPage = (int) $request->input('per_page', 15);
            $perPage = (int) min(100, max(1, $perPage));

            $teams = $this->teamService->getTeams($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الفرق بنجاح',
                'data' => TeamResource::collection($teams->items()),
                'meta' => [
                    'total' => $teams->total(),
                    'count' => $teams->count(),
                    'per_page' => $teams->perPage(),
                    'current_page' => $teams->currentPage(),
                    'last_page' => $teams->lastPage(),
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
     * project_management only (via route middleware)
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $team = $this->teamService->storeTeam($validated, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الفريق بنجاح',
                'data' => new TeamResource($team),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $team = $this->teamService->getTeamById($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الفريق بنجاح',
                'data' => new TeamResource($team),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/project_management/teams/members/{teamId}
     * Query: per_page, team_group_id أو group_id (نفس المجموعة داخل الفريق)
     */
    public function members(Request $request, int $teamId): JsonResponse
    {
        try {
            $team = $this->teamService->getTeamById($teamId);
            $perPage = (int) $request->input('per_page', 15);
            $teamGroupId = $request->filled('team_group_id')
                ? (int) $request->input('team_group_id')
                : ($request->filled('group_id') ? (int) $request->input('group_id') : null);
            if ($teamGroupId !== null && $teamGroupId < 1) {
                $teamGroupId = null;
            }
            $members = $this->teamService->getTeamMembers($teamId, $perPage, $teamGroupId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب أعضاء الفريق بنجاح',
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'data' => UserResource::collection($members->items()),
                'meta' => [
                    'total' => $members->total(),
                    'count' => $members->count(),
                    'per_page' => $members->perPage(),
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $statusCode = str_contains($message, 'Team not found')
                || str_contains($message, 'No query results')
                ? 404
                : 500;
            if (str_contains($message, 'المجموعة غير موجودة') || str_contains($message, 'لا تتبع')) {
                $statusCode = 422;
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }
    }

    /**
     * GET /api/project_management/team-groups/{groupId}/members
     * List members in this group; team is resolved from the group (no team id in URL).
     */
    public function membersOfTeamGroup(Request $request, int $groupId): JsonResponse
    {
        $group = TeamGroup::query()->findOrFail($groupId);
        $request->merge(['team_group_id' => $groupId]);

        return $this->members($request, (int) $group->team_id);
    }

    /**
     * DELETE /api/project_management/team-groups/{groupId}/members/{userId}
     * Remove user from the group only; they remain on the team.
     */
    public function removeMemberFromTeamGroup(int $groupId, int $userId): JsonResponse
    {
        try {
            $group = TeamGroup::query()->findOrFail($groupId);
            $user = $this->teamService->removeUserFromGroupOnly((int) $group->team_id, $groupId, $userId);
            $team = $this->teamService->getTeamById((int) $group->team_id);

            return response()->json([
                'success' => true,
                'message' => 'تمت إزالة العضو من المجموعة مع بقائه ضمن الفريق.',
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'team_id' => $user->team_id,
                    'team_group_id' => $user->team_group_id,
                ],
            ], 200);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code = 500;
            if (str_contains($message, 'الموظف غير ضمن')) {
                $code = 422;
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $code);
        }
    }

    /**
     * GET /api/project_management/teams/sales-without-team
     * Sales users with no team (available to assign).
     */
    public function salesWithoutTeam(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $search = $request->input('search');
            $search = is_string($search) ? trim($search) : null;
            if ($search === '') {
                $search = null;
            }

            $users = $this->teamService->getSalesUsersWithoutTeam($perPage, $search);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب موظفي المبيعات غير المرتبطين بفريق بنجاح',
                'data' => UserResource::collection($users->items()),
                'meta' => [
                    'total' => $users->total(),
                    'count' => $users->count(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function contracts(TeamContractsRequest $request, int $teamId): JsonResponse
    {
        try {
            $team = Team::findOrFail($teamId);

            $validated = $request->validated();
            $filters = [
                // filter only by status (from query param)
                'status' => $validated['status'] ?? null,
            ];

            $perPage = (int) ($validated['per_page'] ?? 15);

            $contracts = $this->contractService->getContractsByTeam($teamId, $filters, $perPage);
            $teamMeta = $this->contractService->getTeamContractsMeta($teamId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب عقود الفريق بنجاح',
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'data' => ContractIndexResource::collection($contracts->items()),
                'meta' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                    'team_contracts' => $teamMeta,
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


    public function contractLocations(TeamContractsRequest $request, int $teamId): JsonResponse
    {
        try {
            $team = Team::findOrFail($teamId);
            $validated = $request->validated();

            $perPage = (int) ($validated['per_page'] ?? 200);
            $status = $validated['status'] ?? null;

            $rows = $this->contractService->getContractLocationsByTeam($teamId, $status, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب مواقع عقود الفريق بنجاح',
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'data' => collect($rows->items())->map(function ($row) {
                    return [
                        'contract_id' => (int) $row->contract_id,
                        'project_name' => $row->project_name,
                        'status' => $row->status,
                        'location_url' => $row->location_url,
                    ];
                }),
                'meta' => [
                    'total' => $rows->total(),
                    'count' => $rows->count(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
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
     * HR: average sales by team (sold units / sales employees in team)
     */
    public function salesAverage(Request $request, int $teamId): JsonResponse
    {
        try {
            $data = $this->teamService->getSalesAverageByTeam($teamId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب متوسط المبيعات للفريق بنجاح',
                'data' => $data,
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
     * project_management only (via route middleware)
     */
    public function update(UpdateTeamRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            $team = $this->teamService->updateTeam($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الفريق بنجاح',
                'data' => new TeamResource($team),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * project_management only (via route middleware)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->teamService->deleteTeam($id);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الفريق بنجاح',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Add a member to the team (sales users only).
     * POST /api/project_management/teams/{teamId}/members
     */
    public function assignMember(AssignSalesTeamMemberRequest $request, int $teamId): JsonResponse
    {
        try {
            $v = $request->validated();
            $user = $this->teamService->assignSalesMemberToTeamGroup(
                $teamId,
                (int) $v['team_group_id'],
                (int) $v['user_id']
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة عضو المبيعات إلى المجموعة داخل الفريق بنجاح',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_type' => $user->type,
                    'team_id' => $user->team_id,
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
     * POST /api/project_management/teams/{teamId}/sales-leader
     * At most one user with type sales_leader per team; user must be type sales_leader.
     */
    public function assignSalesLeader(AssignTeamSalesLeaderRequest $request, int $teamId): JsonResponse
    {
        try {
            $v = $request->validated();
            $user = $this->teamService->assignSalesLeaderToTeam($teamId, (int) $v['user_id']);

            return response()->json([
                'success' => true,
                'message' => 'تم تعيين قائد المبيعات للفريق بنجاح',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_type' => $user->type,
                    'team_id' => $user->team_id,
                    'team_group_id' => $user->team_group_id,
                ],
            ], 200);
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'فقط المستخدمون') || str_contains($message, 'يوجد بالفعل')) {
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
     * GET /api/project_management/teams/sales-leaders?team_id=&per_page=&search=
     * List users with type sales_leader; filter by team_id (optional), search name/email/phone.
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
     * DELETE /api/project_management/teams/{teamId}/sales-leader/{userId}
     * Remove sales leader from the team (user must be type sales_leader on that team).
     */
    public function removeSalesLeader(int $teamId, int $userId): JsonResponse
    {
        try {
            $this->teamService->removeSalesLeaderFromTeam($teamId, $userId);

            return response()->json([
                'success' => true,
                'message' => 'تمت إزالة قائد المبيعات من الفريق بنجاح',
            ], 200);
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'ليس من نوع') || str_contains($message, 'ليس مرتبطاً')) {
                $status = 422;
            } elseif (str_contains($message, 'No query results') || str_contains($message, 'غير موجود')) {
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
     * Remove a member from the team.
     * DELETE /api/project_management/teams/{teamId}/members/{userId}
     */
    public function removeMember(int $teamId, int $userId): JsonResponse
    {
        try {
            $this->teamService->removeMemberFromTeam($teamId, $userId);

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

    public function import_csv(ImportTeamsCsv $request): JsonResponse
    {
        $file = $request->file('file');
        $header = TabularImportReader::peekHeader($file->getRealPath());

        if ($header === []) {
            return response()->json([
                'success' => false,
                'message' => 'The file is empty or has no header row.',
            ], 422);
        }

        $required = ['name'];
        $missing = array_diff($required, $header);

        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'The file is missing required columns: ' . implode(', ', $missing),
            ], 422);
        }

        $path = $file->store('csv_imports/teams', 'local');

        $csvImport = CsvImport::create([
            'type'        => CsvImport::TYPE_TEAMS,
            'uploaded_by' => Auth::id(),
            'file_path'   => $path,
            'status'      => CsvImport::STATUS_PENDING,
        ]);

        return $this->runCsvImport(
            $csvImport,
            fn () => ProcessTeamsCsv::dispatchSync($csvImport->id, Auth::id()),
            fn () => ProcessTeamsCsv::dispatch($csvImport->id, Auth::id())
        );
    }
}
