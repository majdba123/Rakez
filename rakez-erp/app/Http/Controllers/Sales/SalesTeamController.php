<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\RateTeamMemberRequest;
use App\Services\Sales\SalesTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesTeamController extends Controller
{
    public function __construct(
        private SalesTeamService $teamService
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
}
