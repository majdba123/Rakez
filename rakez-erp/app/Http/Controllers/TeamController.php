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

    /**
     * Get contracts for a team with pagination + meta and optional status filter.
     * Query params: status, city, district, project_name, per_page
     */
    // Clean + secure:
    // GET /api/teams/{teamId}/contracts?status=pending&per_page=15
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


