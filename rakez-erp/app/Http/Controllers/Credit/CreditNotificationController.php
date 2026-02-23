<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

/**
 * Tab 2: Credit Notifications (استقبال إشعارات)
 * Receives: new negotiation, price approval/rejection, down payment confirm,
 * reservation confirmed, deadline expired, evacuation complete.
 */
class CreditNotificationController extends Controller
{
    /**
     * Get credit user's notifications.
     * GET /credit/notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'status' => 'nullable|in:pending,read',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = UserNotification::where('user_id', $request->user()->id);

            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->input('from_date'));
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->input('to_date'));
            }
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            $perPage = min((int) $request->input('per_page', 15), 100);
            $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإشعارات بنجاح',
                'data' => $notifications->items(),
                'meta' => [
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
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
     * Mark notification as read.
     * POST /credit/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $notification = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
            $notification->update(['status' => 'read']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الإشعار بنجاح',
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
     * Mark all notifications as read.
     * POST /credit/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            UserNotification::where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->update(['status' => 'read']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث جميع الإشعارات بنجاح',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
