<?php

namespace App\Services\Notification;

use App\Events\PublicNotificationEvent;
use App\Events\UserNotificationEvent;
use App\Models\AdminNotification;
use App\Models\UserNotification;
use App\Models\User;

class NotificationAdminService
{
    public function sendToUser(int $userId, string $message): UserNotification
    {
        $notification = UserNotification::create([
            'user_id' => $userId,
            'message' => $message,
            'status' => 'pending',
        ]);

        event(new UserNotificationEvent($userId, $message));

        return $notification;
    }

    public function sendPublic(string $message): UserNotification
    {
        $notification = UserNotification::create([
            'user_id' => null,
            'message' => $message,
            'status' => 'pending',
        ]);

        event(new PublicNotificationEvent($message));

        return $notification;
    }

    public function sendAdmin(int $userId, string $message): AdminNotification
    {
        return AdminNotification::create([
            'user_id' => $userId,
            'message' => $message,
            'status' => 'pending',
        ]);
    }

    public function markUserNotificationAsRead(UserNotification $notification): UserNotification
    {
        $notification->markAsRead();

        return $notification->fresh();
    }

    public function markAdminNotificationAsRead(AdminNotification $notification): AdminNotification
    {
        $notification->markAsRead();

        return $notification->fresh();
    }

    public function markAllUserNotificationsAsRead(?int $userId = null): int
    {
        $query = UserNotification::query()->where('status', 'pending');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->update(['status' => 'read']);
    }

    public function markAllAdminNotificationsAsRead(?int $userId = null): int
    {
        $query = AdminNotification::query()->where('status', 'pending');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->update(['status' => 'read']);
    }

    /**
     * @return array{total:int, items: array<int, array<string, mixed>>}
     */
    public function getPrivateNotificationsForAiSkill(User $user, int $perPage = 10): array
    {
        $items = UserNotification::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(min(max($perPage, 1), 25))
            ->get()
            ->map(fn (UserNotification $notification) => [
                'id' => $notification->id,
                'message' => $notification->message,
                'status' => $notification->status,
                'event_type' => $notification->event_type,
                'context' => $notification->context,
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->all();

        return [
            'total' => UserNotification::query()->where('user_id', $user->id)->count(),
            'items' => $items,
        ];
    }

    /**
     * @return array{total:int, items: array<int, array<string, mixed>>}
     */
    public function getPublicNotificationsForAiSkill(int $perPage = 10): array
    {
        $items = UserNotification::query()
            ->whereNull('user_id')
            ->latest('created_at')
            ->limit(min(max($perPage, 1), 25))
            ->get()
            ->map(fn (UserNotification $notification) => [
                'id' => $notification->id,
                'message' => $notification->message,
                'status' => $notification->status,
                'event_type' => $notification->event_type,
                'context' => $notification->context,
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->all();

        return [
            'total' => UserNotification::query()->whereNull('user_id')->count(),
            'items' => $items,
        ];
    }
}
