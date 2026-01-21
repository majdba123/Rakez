<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Services\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TeamController extends Controller
{
    protected TeamService $teamService;

    public function __construct(TeamService $teamService)
    {
        $this->teamService = $teamService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
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


