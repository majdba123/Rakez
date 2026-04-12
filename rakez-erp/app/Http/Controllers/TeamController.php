<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\AssignSalesTeamMemberRequest;
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
     */
    public function members(Request $request, int $teamId): JsonResponse
    {
        try {
            $team = $this->teamService->getTeamById($teamId);
            $perPage = (int) $request->input('per_page', 15);
            $members = $this->teamService->getTeamMembers($teamId, $perPage);

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

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
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
            $user = $this->teamService->assignSalesMemberToTeam($teamId, (int) $request->validated('user_id'));

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة عضو المبيعات إلى الفريق بنجاح',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_type' => $user->type,
                    'team_id' => $teamId,
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

        ProcessTeamsCsv::dispatch($csvImport->id, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الملف وبدأ الاستيراد.',
            'data'    => [
                'import_id' => $csvImport->id,
                'status'    => $csvImport->status,
            ],
        ], 202);
    }

    public function import_status(int $id): JsonResponse
    {
        $csvImport = CsvImport::where('id', $id)
            ->where('type', CsvImport::TYPE_TEAMS)
            ->first();

        if (!$csvImport) {
            return response()->json([
                'success' => false,
                'message' => 'سجل الاستيراد غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $csvImport->id,
                'status'          => $csvImport->status,
                'total_rows'      => $csvImport->total_rows,
                'processed_rows'  => $csvImport->processed_rows,
                'successful_rows' => $csvImport->successful_rows,
                'failed_rows'     => $csvImport->failed_rows,
                'row_errors'      => $csvImport->row_errors,
                'error_message'   => $csvImport->error_message,
            ],
        ]);
    }
}
