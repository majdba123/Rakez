<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\TeamContractsRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\Contract\ContractIndexResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Http\Responses\ApiResponse;
use App\Services\Contract\ContractService;
use App\Services\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            $perPage = ApiResponse::getPerPage($request, 15, 100);

            $teams = $this->teamService->getTeams($filters, $perPage);

            $data = TeamResource::collection($teams->items())->resolve();
            return ApiResponse::success($data, 'تم جلب الفرق بنجاح', 200, [
                'pagination' => [
                    'total' => $teams->total(),
                    'count' => $teams->count(),
                    'per_page' => $teams->perPage(),
                    'current_page' => $teams->currentPage(),
                    'total_pages' => $teams->lastPage(),
                    'has_more_pages' => $teams->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
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

            return ApiResponse::created(new TeamResource($team), 'تم إنشاء الفريق بنجاح');
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $team = $this->teamService->getTeamById($id);

            return ApiResponse::success(new TeamResource($team), 'تم جلب الفريق بنجاح');
        } catch (Exception $e) {
            return ApiResponse::notFound($e->getMessage());
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

            $perPage = ApiResponse::getPerPage($request, 15, 100);

            $contracts = $this->contractService->getContractsByTeam($teamId, $filters, $perPage);
            $teamMeta = $this->contractService->getTeamContractsMeta($teamId);

            $data = ContractIndexResource::collection($contracts->items())->resolve();
            return ApiResponse::success($data, 'تم جلب عقود الفريق بنجاح', 200, [
                'team' => ['id' => $team->id, 'name' => $team->name],
                'pagination' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'total_pages' => $contracts->lastPage(),
                    'has_more_pages' => $contracts->hasMorePages(),
                ],
                'team_contracts' => $teamMeta,
            ]);
        } catch (Exception $e) {
            return str_contains($e->getMessage(), 'No query results')
                ? ApiResponse::notFound($e->getMessage())
                : ApiResponse::serverError($e->getMessage());
        }
    }


    public function contractLocations(TeamContractsRequest $request, int $teamId): JsonResponse
    {
        try {
            $team = Team::findOrFail($teamId);
            $validated = $request->validated();

            $perPage = ApiResponse::getPerPage($request, 200, 500);
            $status = $validated['status'] ?? null;

            $rows = $this->contractService->getContractLocationsByTeam($teamId, $status, $perPage);

            $data = collect($rows->items())->map(function ($row) {
                return [
                    'contract_id' => (int) $row->contract_id,
                    'project_name' => $row->project_name,
                    'status' => $row->status,
                    'lat' => $row->lat !== null ? (float) $row->lat : null,
                    'lng' => $row->lng !== null ? (float) $row->lng : null,
                ];
            })->values()->all();
            return ApiResponse::success($data, 'تم جلب مواقع عقود الفريق بنجاح', 200, [
                'team' => ['id' => $team->id, 'name' => $team->name],
                'pagination' => [
                    'total' => $rows->total(),
                    'count' => $rows->count(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'total_pages' => $rows->lastPage(),
                    'has_more_pages' => $rows->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return str_contains($e->getMessage(), 'No query results')
                ? ApiResponse::notFound($e->getMessage())
                : ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * HR: average sales by team (sold units / sales employees in team)
     */
    public function salesAverage(Request $request, int $teamId): JsonResponse
    {
        try {
            $data = $this->teamService->getSalesAverageByTeam($teamId);

            return ApiResponse::success($data, 'تم جلب متوسط المبيعات للفريق بنجاح');
        } catch (Exception $e) {
            return str_contains($e->getMessage(), 'No query results')
                ? ApiResponse::notFound($e->getMessage())
                : ApiResponse::serverError($e->getMessage());
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

            return ApiResponse::success(new TeamResource($team), 'تم تحديث الفريق بنجاح');
        } catch (Exception $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * project_management only (via route middleware)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->teamService->deleteTeam($id);

            return ApiResponse::success(null, 'تم حذف الفريق بنجاح');
        } catch (Exception $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }
}


