<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\AdminNotification;
use App\Models\PublicNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    // ==========================================
    // USER NOTIFICATIONS - إشعارات المستخدم
    // ==========================================

    /**
     * Get user's personal notifications
     */
    public function userIndex(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->userNotifications()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'message' => 'تم جلب الإشعارات بنجاح',
            'data' => $notifications,
        ]);
    }

    /**
     * Get user's unread notifications
     */
    public function userUnread(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->userNotifications()
            ->unread()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'تم جلب الإشعارات غير المقروءة',
            'data' => $notifications,
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Get user's unread count
     */
    public function userUnreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadUserNotificationsCount(),
        ]);
    }

    /**
     * Mark user notification as read
     */
    public function userMarkAsRead(Request $request, $id): JsonResponse
    {
        $notification = $request->user()->userNotifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'message' => 'تم تحديد الإشعار كمقروء',
        ]);
    }

    /**
     * Mark all user notifications as read
     */
    public function userMarkAllAsRead(Request $request): JsonResponse
    {
        $request->user()->userNotifications()->unread()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'تم تحديد جميع الإشعارات كمقروءة',
        ]);
    }

    /**
     * Delete user notification
     */
    public function userDestroy(Request $request, $id): JsonResponse
    {
        $notification = $request->user()->userNotifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'تم حذف الإشعار',
        ]);
    }

    // ==========================================
    // ADMIN NOTIFICATIONS - إشعارات المدراء
    // ==========================================

    /**
     * Get admin notifications (admin only)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = AdminNotification::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add read status for current admin
        $notifications->getCollection()->transform(function ($notification) use ($user) {
            $notification->is_read = $notification->isReadBy($user);
            return $notification;
        });

        return response()->json([
            'message' => 'تم جلب إشعارات المدراء',
            'data' => $notifications,
        ]);
    }

    /**
     * Get unread admin notifications count
     */
    public function adminUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $readIds = $user->adminNotificationReads()->pluck('admin_notification_id');
        $count = AdminNotification::whereNotIn('id', $readIds)->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Mark admin notification as read
     */
    public function adminMarkAsRead(Request $request, $id): JsonResponse
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->markAsReadBy($request->user());

        return response()->json([
            'message' => 'تم تحديد الإشعار كمقروء',
        ]);
    }

    /**
     * Mark all admin notifications as read
     */
    public function adminMarkAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $readIds = $user->adminNotificationReads()->pluck('admin_notification_id');

        $unreadNotifications = AdminNotification::whereNotIn('id', $readIds)->get();

        foreach ($unreadNotifications as $notification) {
            $notification->markAsReadBy($user);
        }

        return response()->json([
            'message' => 'تم تحديد جميع الإشعارات كمقروءة',
        ]);
    }

    /**
     * Create admin notification (admin only)
     */
    public function adminStore(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|max:50',
            'data' => 'nullable|array',
        ]);

        $notification = AdminNotification::create([
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type ?? 'info',
            'data' => $request->data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'تم إنشاء الإشعار بنجاح',
            'data' => $notification,
        ], 201);
    }

    /**
     * Delete admin notification (admin only)
     */
    public function adminDestroy($id): JsonResponse
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'تم حذف الإشعار',
        ]);
    }

    // ==========================================
    // PUBLIC NOTIFICATIONS - إشعارات عامة
    // ==========================================

    /**
     * Get active public notifications (for all users)
     */
    public function publicIndex(): JsonResponse
    {
        $notifications = PublicNotification::active()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'تم جلب الإشعارات العامة',
            'data' => $notifications,
        ]);
    }

    /**
     * Get all public notifications (admin only)
     */
    public function publicAdminIndex(Request $request): JsonResponse
    {
        $notifications = PublicNotification::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'message' => 'تم جلب جميع الإشعارات العامة',
            'data' => $notifications,
        ]);
    }

    /**
     * Create public notification (admin only)
     */
    public function publicStore(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|max:50',
            'data' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ]);

        $notification = PublicNotification::create([
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type ?? 'info',
            'data' => $request->data,
            'is_active' => $request->is_active ?? true,
            'starts_at' => $request->starts_at,
            'expires_at' => $request->expires_at,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'تم إنشاء الإشعار العام بنجاح',
            'data' => $notification,
        ], 201);
    }

    /**
     * Update public notification (admin only)
     */
    public function publicUpdate(Request $request, $id): JsonResponse
    {
        $notification = PublicNotification::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'data' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $notification->update($request->only([
            'title', 'message', 'type', 'data',
            'is_active', 'starts_at', 'expires_at'
        ]));

        return response()->json([
            'message' => 'تم تحديث الإشعار',
            'data' => $notification,
        ]);
    }

    /**
     * Delete public notification (admin only)
     */
    public function publicDestroy($id): JsonResponse
    {
        $notification = PublicNotification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'تم حذف الإشعار',
        ]);
    }

    // ==========================================
    // COMBINED - جلب كل الإشعارات
    // ==========================================

    /**
     * Get all notifications for user (personal + public)
     */
    public function getAllForUser(Request $request): JsonResponse
    {
        $user = $request->user();

        // User's personal notifications
        $userNotifications = $user->userNotifications()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($n) {
                $n->source = 'user';
                return $n;
            });

        // Active public notifications
        $publicNotifications = PublicNotification::active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($n) {
                $n->source = 'public';
                return $n;
            });

        // Merge and sort
        $all = $userNotifications->concat($publicNotifications)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'message' => 'تم جلب جميع الإشعارات',
            'data' => $all,
            'unread_count' => $user->unreadUserNotificationsCount(),
        ]);
    }

    /**
     * Get all notifications for admin (personal + admin + public)
     */
    public function getAllForAdmin(Request $request): JsonResponse
    {
        $user = $request->user();

        // User's personal notifications
        $userNotifications = $user->userNotifications()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($n) {
                $n->source = 'user';
                return $n;
            });

        // Admin notifications with read status
        $readIds = $user->adminNotificationReads()->pluck('admin_notification_id');
        $adminNotifications = AdminNotification::orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($n) use ($readIds) {
                $n->source = 'admin';
                $n->is_read = $readIds->contains($n->id);
                return $n;
            });

        // Active public notifications
        $publicNotifications = PublicNotification::active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($n) {
                $n->source = 'public';
                return $n;
            });

        // Merge and sort
        $all = $userNotifications
            ->concat($adminNotifications)
            ->concat($publicNotifications)
            ->sortByDesc('created_at')
            ->values();

        // Unread counts
        $unreadAdmin = AdminNotification::whereNotIn('id', $readIds)->count();

        return response()->json([
            'message' => 'تم جلب جميع الإشعارات',
            'data' => $all,
            'unread_user' => $user->unreadUserNotificationsCount(),
            'unread_admin' => $unreadAdmin,
        ]);
    }
}
