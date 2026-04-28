<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\StoreTeamGroupRequest;
use App\Http\Requests\Team\UpdateTeamGroupRequest;
use App\Http\Resources\TeamGroupResource;
use App\Services\Team\TeamGroupService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamGroupController extends Controller
{
    public function __construct(
        protected TeamGroupService $teamGroupService
    ) {}

    /**
     * List groups, optionally filtered by team_id.
     * Query: team_id (optional), per_page (1–100, default 15)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $teamId = $request->filled('team_id') ? (int) $request->input('team_id') : null;

            $rows = $this->teamGroupService->paginate($teamId, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب مجموعات الفرق بنجاح',
                'data' => TeamGroupResource::collection($rows->items()),
                'meta' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreTeamGroupRequest $request): JsonResponse
    {
        try {
            $group = $this->teamGroupService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المجموعة وربطها بالفريق بنجاح',
                'data' => new TeamGroupResource($group),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $group = $this->teamGroupService->findByIdOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب المجموعة بنجاح',
                'data' => new TeamGroupResource($group),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'المجموعة غير موجودة.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateTeamGroupRequest $request, int $id): JsonResponse
    {
        try {
            $group = $this->teamGroupService->findByIdOrFail($id);
            $updated = $this->teamGroupService->update($group, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المجموعة بنجاح',
                'data' => new TeamGroupResource($updated),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'المجموعة غير موجودة.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $group = $this->teamGroupService->findByIdOrFail($id);
            $this->teamGroupService->delete($group);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المجموعة بنجاح',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'المجموعة غير موجودة.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
