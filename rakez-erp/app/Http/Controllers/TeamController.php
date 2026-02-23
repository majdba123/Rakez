<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\TeamContractsRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\Contract\ContractIndexResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
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
                        'lat' => $row->lat !== null ? (float) $row->lat : null,
                        'lng' => $row->lng !== null ? (float) $row->lng : null,
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
}


