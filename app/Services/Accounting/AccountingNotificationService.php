<?php

namespace App\Services\Accounting;

use App\Models\UserNotification;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\Deposit;
use App\Models\Commission;

class AccountingNotificationService
{
    /**
     * Notify accounting department when a unit is reserved.
     */
    public function notifyUnitReserved(SalesReservation $reservation): void
    {
        $message = sprintf(
            'تم حجز وحدة جديدة - المشروع: %s - الوحدة: %s - العميل: %s',
            $reservation->contract?->project_name ?? '-',
            $reservation->contractUnit?->unit_number ?? '-',
            $reservation->client_name ?? '-'
        );

        $this->notifyAccountingUsers($message, 'unit_reserved');
    }

    /**
     * Notify accounting department when a deposit is received.
     */
    public function notifyDepositReceived(Deposit $deposit): void
    {
        $message = sprintf(
            'تم استلام عربون بمبلغ %s ريال سعودي - المشروع: %s - الوحدة: %s - العميل: %s',
            $deposit->amount,
            $deposit->contract?->project_name ?? '-',
            $deposit->contractUnit?->unit_number ?? '-',
            $deposit->client_name ?? '-'
        );

        $this->notifyAccountingUsers($message, 'deposit_received');
    }

    /**
     * Notify accounting department when a unit is vacated.
     */
    public function notifyUnitVacated(SalesReservation $reservation): void
    {
        $message = sprintf(
            'تم إخلاء وحدة - المشروع: %s - الوحدة: %s',
            $reservation->contract?->project_name ?? '-',
            $reservation->contractUnit?->unit_number ?? '-'
        );

        $this->notifyAccountingUsers($message, 'unit_vacated');
    }

    /**
     * Notify accounting department when a reservation is cancelled.
     */
    public function notifyReservationCancelled(SalesReservation $reservation): void
    {
        $message = sprintf(
            'تم إلغاء حجز - المشروع: %s - الوحدة: %s - العميل: %s',
            $reservation->contract?->project_name ?? '-',
            $reservation->contractUnit?->unit_number ?? '-',
            $reservation->client_name ?? '-'
        );

        $this->notifyAccountingUsers($message, 'reservation_cancelled');
    }

    /**
     * Notify accounting department when a commission is confirmed.
     */
    public function notifyCommissionConfirmed(Commission $commission): void
    {
        $message = sprintf(
            'تم تأكيد عمولة - المبلغ الصافي: %s ريال سعودي - المشروع: %s - الوحدة: %s',
            $commission->net_amount,
            $commission->salesReservation?->contract?->project_name ?? '-',
            $commission->contractUnit?->unit_number ?? '-'
        );

        $this->notifyAccountingUsers($message, 'commission_confirmed');
    }

    /**
     * Notify accounting department when commission is received from owner.
     */
    public function notifyCommissionReceivedFromOwner(Commission $commission): void
    {
        if ($commission->commission_source !== 'owner') {
            return; // Only notify for owner-paid commissions
        }

        $message = sprintf(
            'تم استلام عمولة من المالك - المبلغ: %s ريال سعودي - المشروع: %s - الوحدة: %s',
            $commission->net_amount,
            $commission->salesReservation?->contract?->project_name ?? '-',
            $commission->contractUnit?->unit_number ?? '-'
        );

        $this->notifyAccountingUsers($message, 'commission_received');
    }

    /**
     * Get accounting notifications with filters.
     */
    public function getAccountingNotifications(int $userId, array $filters = [])
    {
        $query = UserNotification::where('user_id', $userId);

        // Filter by date range
        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by notification type: prefer event_type when set, else message keyword
        if (isset($filters['type'])) {
            $keyword = $this->getTypeKeyword($filters['type']);
            $query->where(function ($q) use ($filters, $keyword) {
                $q->where('event_type', $filters['type'])
                    ->orWhere('message', 'like', '%' . $keyword . '%');
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
        $paginator->setCollection($paginator->getCollection()->map(fn ($n) => $this->transformNotificationForList($n)));

        return $paginator;
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $notificationId): void
    {
        $notification = UserNotification::findOrFail($notificationId);
        $notification->update(['status' => 'read']);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int $userId): void
    {
        UserNotification::where('user_id', $userId)
            ->where('status', 'pending')
            ->update(['status' => 'read']);
    }

    /**
     * Send notification to all accounting users.
     */
    protected function notifyAccountingUsers(string $message, ?string $eventType = null): void
    {
        $accountingUsers = User::where('type', 'accounting')
            ->where('is_active', true)
            ->get();

        foreach ($accountingUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'status' => 'pending',
                'event_type' => $eventType,
            ]);
        }
    }

    /**
     * Get keyword for notification type filtering.
     */
    protected function getTypeKeyword(string $type): string
    {
        $keywords = [
            'unit_reserved' => 'حجز وحدة',
            'deposit_received' => 'استلام عربون',
            'unit_vacated' => 'إخلاء وحدة',
            'reservation_cancelled' => 'إلغاء حجز',
            'commission_confirmed' => 'تأكيد عمولة',
            'commission_received' => 'استلام عمولة من المالك',
        ];

        return $keywords[$type] ?? '';
    }

    /**
     * Infer notification type from message content (for legacy notifications with null event_type).
     */
    protected function getTypeFromMessage(string $message): ?string
    {
        $keywords = [
            'unit_reserved' => 'حجز وحدة',
            'deposit_received' => 'استلام عربون',
            'unit_vacated' => 'إخلاء وحدة',
            'reservation_cancelled' => 'إلغاء حجز',
            'commission_confirmed' => 'تأكيد عمولة',
            'commission_received' => 'استلام عمولة من المالك',
        ];

        foreach ($keywords as $type => $keyword) {
            if (str_contains($message, $keyword)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Arabic title for notification type (for list display).
     */
    protected function getTitleForType(string $type): string
    {
        $titles = [
            'unit_reserved' => 'حجز وحدة جديدة',
            'deposit_received' => 'استلام عربون',
            'unit_vacated' => 'إخلاء وحدة',
            'reservation_cancelled' => 'إلغاء حجز',
            'commission_confirmed' => 'تأكيد عمولة',
            'commission_received' => 'استلام عمولة من المالك',
        ];

        return $titles[$type] ?? 'إشعار';
    }

    /**
     * Transform a notification into a flat list item with type and title.
     */
    public function transformNotificationForList(UserNotification $n): array
    {
        $type = $n->event_type ?? $this->getTypeFromMessage($n->message ?? '');
        $title = $type ? $this->getTitleForType($type) : 'إشعار';

        return [
            'id' => $n->id,
            'message' => $n->message,
            'status' => $n->status,
            'type' => $type,
            'title' => $title,
            'created_at' => $n->created_at?->toDateTimeString(),
            'event_type' => $n->event_type,
            'context' => $n->context,
        ];
    }
}
