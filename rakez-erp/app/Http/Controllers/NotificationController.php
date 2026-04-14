<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\AdminNotification;
use App\Events\UserNotificationEvent;
use App\Events\PublicNotificationEvent;
use App\Http\Resources\AdminNotificationResource;
use App\Http\Resources\UserNotificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

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
        $query = AdminNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        return $this->paginateNotifications($query, $request, 'admin');
    }

    /**
     * Paginate notifications query and return JSON response.
     *
     * @param Builder $query
     * @param Request $request
     * @param string $type 'admin'|'user'
     */
    private function paginateNotifications(Builder $query, Request $request, string $type = 'user'): JsonResponse
    {
        $perPage = ApiResponse::getPerPage($request);
        $paginator = $query->paginate($perPage);

        $resource = $type === 'admin'
            ? AdminNotificationResource::class
            : UserNotificationResource::class;

        return response()->json([
            'data' => $resource::collection($paginator->items()),
            'meta' => [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ],
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
    public function getUserNotificationsByAdmin($userId, Request $request): JsonResponse
    {
        $query = UserNotification::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        return $this->paginateNotifications($query, $request, 'user');
    }

    /**
     * Get all public notifications (admin view)
     */
    public function getAllPublicNotifications(Request $request): JsonResponse
    {
        $query = UserNotification::whereNull('user_id')
            ->orderBy('created_at', 'desc');

        return $this->paginateNotifications($query, $request, 'user');
    }

    // ==========================================
    // USER APIs
    // ==========================================

    /**
     * Get user's private notifications only
     */
    public function getUserPrivateNotifications(Request $request): JsonResponse
    {
        $query = UserNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        return $this->paginateNotifications($query, $request, 'user');
    }

    /**
     * Get public notifications
     */
    public function getPublicNotifications(Request $request): JsonResponse
    {
        $query = UserNotification::whereNull('user_id')
            ->orderBy('created_at', 'desc');

        return $this->paginateNotifications($query, $request, 'user');
    }

    /**
     * Mark all user notifications as read
     */
    public function userMarkAllAsRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $updated = UserNotification::where('user_id', $userId)
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);

        return response()->json([
            'success' => true,
            'message' => 'تم تعليم جميع الإشعارات كمقروءة',
            'updated_count' => $updated,
        ]);
    }

    /**
     * Mark specific notification as read.
     *
     * Supports:
     * - Integer ids: persisted {@see UserNotification} rows for the current user.
     * - Client-only ids prefixed with `local-`: no DB row; returns 200 so SPA optimistic UI does not 404.
     */
    public function userMarkAsRead(Request $request, string $id): JsonResponse
    {
        if (str_starts_with($id, 'local-')) {
            $readAt = Carbon::now();

            return response()->json([
                'success' => true,
                'message' => 'تم تعليم الإشعار كمقروء',
                'data' => [
                    'id' => $id,
                    'read_at' => $readAt->toIso8601String(),
                    'client_only' => true,
                ],
            ]);
        }

        if (! ctype_digit($id)) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود أو لا يخصك',
            ], 404);
        }

        $notification = UserNotification::where('user_id', $request->user()->id)
            ->whereKey((int) $id)
            ->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود أو لا يخصك',
            ], 404);
        }

        if ($notification->status !== 'read') {
            $notification->update(['status' => 'read']);
        }

        $fresh = $notification->fresh();

        return response()->json([
            'success' => true,
            'message' => 'تم تعليم الإشعار كمقروء',
            'data' => array_merge(
                (new UserNotificationResource($fresh))->resolve(),
                [
                    'read_at' => ($fresh->updated_at ?? Carbon::now())->toIso8601String(),
                    'client_only' => false,
                ]
            ),
        ]);
    }
}
