<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\AdminNotification;
use App\Events\PublicNotificationCreated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    // ==========================================
    // ADMIN NOTIFICATIONS - إشعارات المدراء
    // ==========================================

    /**
     * Get admin's notifications
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $notifications = AdminNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'message' => 'تم جلب إشعارات المدير',
            'data' => $notifications,
        ]);
    }

    /**
     * Get admin's pending (unread) notifications
     */
    public function adminPending(Request $request): JsonResponse
    {
        $notifications = AdminNotification::where('user_id', $request->user()->id)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'الإشعارات غير المقروءة',
            'data' => $notifications,
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Get admin's pending count
     */
    public function adminPendingCount(Request $request): JsonResponse
    {
        $count = AdminNotification::where('user_id', $request->user()->id)
            ->pending()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark admin notification as read
     */
    public function adminMarkAsRead(Request $request, $id): JsonResponse
    {
        $notification = AdminNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['message' => 'تم تحديد كمقروء']);
    }

    /**
     * Mark all admin notifications as read
     */
    public function adminMarkAllAsRead(Request $request): JsonResponse
    {
        AdminNotification::where('user_id', $request->user()->id)
            ->pending()
            ->update(['status' => 'read']);

        return response()->json(['message' => 'تم تحديد الكل كمقروء']);
    }

    /**
     * Delete admin notification
     */
    public function adminDestroy(Request $request, $id): JsonResponse
    {
        $notification = AdminNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    // ==========================================
    // USER NOTIFICATIONS - إشعارات المستخدمين
    // ==========================================

    /**
     * Get user's notifications (private + public)
     */
    public function userIndex(Request $request): JsonResponse
    {
        $notifications = UserNotification::forUser($request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'message' => 'تم جلب الإشعارات',
            'data' => $notifications,
        ]);
    }

    /**
     * Get user's pending notifications
     */
    public function userPending(Request $request): JsonResponse
    {
        $notifications = UserNotification::forUser($request->user()->id)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'الإشعارات غير المقروءة',
            'data' => $notifications,
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Get user's pending count
     */
    public function userPendingCount(Request $request): JsonResponse
    {
        $count = UserNotification::forUser($request->user()->id)
            ->pending()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark user notification as read
     */
    public function userMarkAsRead(Request $request, $id): JsonResponse
    {
        $notification = UserNotification::forUser($request->user()->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['message' => 'تم تحديد كمقروء']);
    }

    /**
     * Mark all user notifications as read
     */
    public function userMarkAllAsRead(Request $request): JsonResponse
    {
        UserNotification::forUser($request->user()->id)
            ->pending()
            ->update(['status' => 'read']);

        return response()->json(['message' => 'تم تحديد الكل كمقروء']);
    }

    /**
     * Delete user notification (only private ones)
     */
    public function userDestroy(Request $request, $id): JsonResponse
    {
        $notification = UserNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    // ==========================================
    // PUBLIC NOTIFICATIONS - إشعارات عامة
    // ==========================================

    /**
     * Get public notifications (for everyone - no auth needed)
     */
    public function publicIndex(): JsonResponse
    {
        $notifications = UserNotification::public()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'message' => 'الإشعارات العامة',
            'data' => $notifications,
        ]);
    }

    /**
     * Create public notification (admin only) + broadcast
     */
    public function publicStore(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $notification = UserNotification::createPublic(
            message: $request->message,
            title: $request->title,
            data: $request->data
        );

        // Broadcast to public channel
        event(new PublicNotificationCreated($notification));

        return response()->json([
            'message' => 'تم إنشاء الإشعار العام وبثه',
            'data' => $notification,
        ], 201);
    }

    /**
     * Delete public notification (admin only)
     */
    public function publicDestroy($id): JsonResponse
    {
        $notification = UserNotification::public()->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    // ==========================================
    // SEND TO SPECIFIC USER - إرسال لمستخدم محدد
    // ==========================================

    /**
     * Send notification to specific user (admin only)
     */
    public function sendToUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $notification = UserNotification::createForUser(
            userId: $request->user_id,
            message: $request->message,
            title: $request->title,
            data: $request->data
        );

        return response()->json([
            'message' => 'تم إرسال الإشعار للمستخدم',
            'data' => $notification,
        ], 201);
    }
}
