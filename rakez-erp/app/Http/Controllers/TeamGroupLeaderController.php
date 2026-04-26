<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\AssignTeamGroupLeaderRequest;
use App\Http\Resources\TeamGroupLeaderResource;
use App\Services\Team\TeamGroupLeaderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TeamGroupLeaderController extends Controller
{
    public function __construct(
        protected TeamGroupLeaderService $teamGroupLeaderService
    ) {}

    /**
     * List team group leader assignments. Query: team_id, team_group_id, user_id, search (name), per_page
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $filters = array_filter([
                'team_id' => $request->input('team_id'),
                'team_group_id' => $request->input('team_group_id'),
                'user_id' => $request->input('user_id'),
                'search' => $request->input('search'),
            ], fn ($v) => $v !== null && $v !== '');

            $rows = $this->teamGroupLeaderService->paginateWithFilters($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قادة المجموعات بنجاح',
                'data' => TeamGroupLeaderResource::collection($rows->items()),
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

    /**
     * Assign or replace the leader of a team group. Body: { "user_id": 1 }
     */
    public function assign(AssignTeamGroupLeaderRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->validated('user_id');
            $row = $this->teamGroupLeaderService->assignLeader($id, $userId);
            $message = $row->wasRecentlyCreated
                ? 'تم تعيين قائد المجموعة بنجاح.'
                : 'تم تحديث قائد المجموعة بنجاح.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new TeamGroupLeaderResource($row),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'المجموعة غير موجودة. لا يمكن تعيين قائد.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تعيين قائد المجموعة: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the leader from a team group
     */
    public function remove(int $id): JsonResponse
    {
        try {
            $deleted = $this->teamGroupLeaderService->removeLeader($id);
            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد قائد معيّن لهذه المجموعة، لا شيء لإزالته.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء تعيين قائد المجموعة بنجاح.',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'المجموعة غير موجودة. لا يمكن إلغاء التعيين.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إلغاء تعيين القائد: '.$e->getMessage(),
            ], 500);
        }
    }
}
