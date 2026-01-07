<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\AdminNotification;
use App\Events\UserNotificationEvent;
use App\Events\PublicNotificationEvent;
use App\Http\Resources\AdminNotificationResource;
use App\Http\Resources\UserNotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    // ==========================================
    // ADMIN APIs
    // ==========================================

    /**
     * Get admin's notifications
     */
    public function getAdminNotifications(Request $request): JsonResponse
    {
        $notifications = AdminNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => AdminNotificationResource::collection($notifications),
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Send notification to specific user
     */
    public function sendToUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);

        $notification = UserNotification::create([
            'user_id' => $request->user_id,
            'message' => $request->message,
        ]);

        event(new UserNotificationEvent($request->user_id, $request->message));

        return response()->json([
            'message' => 'Notification sent to user',
            'data' => new UserNotificationResource($notification),
        ], 201);
    }

    /**
     * Send public notification
     */
    public function sendPublic(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $notification = UserNotification::create([
            'user_id' => null,
            'message' => $request->message,
        ]);

        event(new PublicNotificationEvent($request->message));

        return response()->json([
            'message' => 'Public notification sent',
            'data' => new UserNotificationResource($notification),
        ], 201);
    }

    /**
     * Get all notifications of specific user (admin view)
     */
    public function getUserNotificationsByAdmin($userId): JsonResponse
    {
        $notifications = UserNotification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => UserNotificationResource::collection($notifications),
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Get all public notifications (admin view)
     */
    public function getAllPublicNotifications(): JsonResponse
    {
        $notifications = UserNotification::whereNull('user_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => UserNotificationResource::collection($notifications),
            'count' => $notifications->count(),
        ]);
    }

    // ==========================================
    // USER APIs
    // ==========================================

    /**
     * Get user's private notifications only
     */
    public function getUserPrivateNotifications(Request $request): JsonResponse
    {
        $notifications = UserNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => UserNotificationResource::collection($notifications),
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Get public notifications
     */
    public function getPublicNotifications(): JsonResponse
    {
        $notifications = UserNotification::whereNull('user_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => UserNotificationResource::collection($notifications),
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Mark all user notifications as read
     */
    public function userMarkAllAsRead(Request $request): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->update(['status' => 'read']);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Mark specific notification as read
     */
    public function userMarkAsRead(Request $request, $id): JsonResponse
    {
        $notification = UserNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['status' => 'read']);

        return response()->json(['message' => 'Notification marked as read']);
    }
}
