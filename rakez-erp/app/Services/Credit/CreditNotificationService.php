<?php

namespace App\Services\Credit;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CreditNotificationService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function listForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = UserNotification::query()
            ->where('user_id', $user->id);

        if (filled($filters['from_date'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (filled($filters['to_date'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function markAsReadForUser(User $user, int $notificationId): UserNotification
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->findOrFail($notificationId);

        $notification->update(['status' => 'read']);

        return $notification->fresh();
    }

    public function markAllAsReadForUser(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'read']);
    }

    public function markAllDepartmentNotificationsAsRead(string $departmentType): int
    {
        return UserNotification::query()
            ->where('status', 'pending')
            ->whereHas('user', fn ($query) => $query->where('type', $departmentType))
            ->update(['status' => 'read']);
    }

    public function markDepartmentNotificationAsRead(string $departmentType, int $notificationId): UserNotification
    {
        $notification = UserNotification::query()
            ->whereHas('user', fn ($query) => $query->where('type', $departmentType))
            ->findOrFail($notificationId);

        if ($notification->status !== 'read') {
            $notification->update(['status' => 'read']);
        }

        return $notification->fresh();
    }
}
