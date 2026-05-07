<?php

namespace App\Services\Sales;

use App\Models\NegotiationApproval;
use App\Models\SalesReservation;
use App\Models\User;
use App\Models\UserNotification;
use App\Events\UserNotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class NegotiationApprovalService
{
    protected ReservationVoucherService $voucherService;

    public function __construct(ReservationVoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * Get pending approvals for managers.
     */
    public function getPendingApprovals(array $filters = []): LengthAwarePaginator
    {
        $query = NegotiationApproval::with([
            'reservation.contract',
            'reservation.contractUnit',
            'reservation.marketingEmployee',
            'requester',
        ])->pending();

        // Filter by contract/project
        if (!empty($filters['contract_id'])) {
            $query->whereHas('reservation', function ($q) use ($filters) {
                $q->where('contract_id', $filters['contract_id']);
            });
        }

        // Filter by requester
        if (!empty($filters['requested_by'])) {
            $query->where('requested_by', $filters['requested_by']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('deadline_at', 'asc')->paginate($perPage);
    }

    /**
     * Approve a negotiation request.
     */
    public function approve(int $approvalId, User $manager, ?string $notes = null): NegotiationApproval
    {
        $approval = NegotiationApproval::with(['reservation', 'requester'])->findOrFail($approvalId);

        if (!$approval->canRespond()) {
            throw new Exception('This negotiation request cannot be responded to (expired or already processed)');
        }

        DB::beginTransaction();
        try {
            // Update approval record
            $approval->update([
                'status' => 'approved',
                'approved_by' => $manager->id,
                'manager_notes' => $notes,
                'responded_at' => now(),
            ]);

            // Update reservation to confirmed
            $reservation = $approval->reservation;
            $reservation->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            // Regenerate voucher with new status
            $voucherPath = $this->voucherService->generate($reservation);
            $reservation->update(['voucher_pdf_path' => $voucherPath]);

            DB::commit();

            // Notify the marketer
            $this->notifyMarketer($approval, 'approved');

            // Notify credit department (view only)
            $this->notifyCreditDepartment($reservation);

            return $approval->fresh(['reservation', 'requester', 'approver']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject a negotiation request.
     */
    public function reject(int $approvalId, User $manager, string $reason): NegotiationApproval
    {
        $approval = NegotiationApproval::with(['reservation', 'requester'])->findOrFail($approvalId);

        if (!$approval->canRespond()) {
            throw new Exception('This negotiation request cannot be responded to (expired or already processed)');
        }

        DB::beginTransaction();
        try {
            // Update approval record
            $approval->update([
                'status' => 'rejected',
                'approved_by' => $manager->id,
                'manager_notes' => $reason,
                'responded_at' => now(),
            ]);

            // Reservation stays under_negotiation, marketer can edit and resubmit
            // Or we could cancel it - business decision

            DB::commit();

            // Notify the marketer
            $this->notifyMarketer($approval, 'rejected');

            return $approval->fresh(['reservation', 'requester', 'approver']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Expire overdue negotiation approvals.
     * Called by scheduled command.
     */
    public function expireOverdue(): int
    {
        $overdue = NegotiationApproval::with(['reservation', 'requester'])
            ->overdue()
            ->get();

        $count = 0;
        foreach ($overdue as $approval) {
            DB::beginTransaction();
            try {
                $approval->update([
                    'status' => 'expired',
                    'responded_at' => now(),
                ]);

                DB::commit();

                // Notify both manager and marketer
                $this->notifyExpiration($approval);

                $count++;
            } catch (Exception $e) {
                DB::rollBack();
                // Log error but continue with others
                \Log::error("Failed to expire negotiation approval {$approval->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Notify the marketer about approval status.
     */
    protected function notifyMarketer(NegotiationApproval $approval, string $status): void
    {
        $statusAr = $status === 'approved' ? 'تمت الموافقة' : 'تم الرفض';
        $message = sprintf(
            'طلب التفاوض للوحدة %s في مشروع %s: %s',
            $approval->reservation->contractUnit->unit_number ?? 'N/A',
            $approval->reservation->contract->project_name ?? 'N/A',
            $statusAr
        );

        if ($status === 'rejected' && $approval->manager_notes) {
            $message .= ' - السبب: ' . $approval->manager_notes;
        }

        $eventType = $status === 'approved' ? 'negotiation_price_approved' : 'negotiation_price_rejected';

        UserNotification::create([
            'user_id' => $approval->requested_by,
            'message' => $message,
            'event_type' => $eventType,
            'context' => [
                'reservation_id' => $approval->reservation_id,
                'approval_id' => $approval->id,
            ],
        ]);

        event(new UserNotificationEvent($approval->requested_by, $message));

        // Notify credit department (الموافقة أو الرفض على السعر)
        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => $eventType,
                'context' => [
                    'reservation_id' => $approval->reservation_id,
                    'approval_id' => $approval->id,
                ],
            ]);
            event(new UserNotificationEvent($user->id, $message));
        }
    }

    /**
     * Notify credit department about confirmed reservation (view only).
     */
    protected function notifyCreditDepartment(SalesReservation $reservation): void
    {
        $creditUsers = User::where('type', 'credit')->get();

        $message = sprintf(
            'حجز تفاوض تم تأكيده: مشروع %s، وحدة %s (للمشاهدة فقط - لا يمكن بدء الإجراءات)',
            $reservation->contract->project_name ?? 'N/A',
            $reservation->contractUnit->unit_number ?? 'N/A'
        );

        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => 'reservation_confirmed',
                'context' => [
                    'reservation_id' => $reservation->id,
                ],
            ]);

            event(new UserNotificationEvent($user->id, $message));
        }
    }

    /**
     * Notify about expired negotiation.
     */
    protected function notifyExpiration(NegotiationApproval $approval): void
    {
        $message = sprintf(
            'انتهت مهلة الرد على طلب التفاوض للوحدة %s في مشروع %s',
            $approval->reservation->contractUnit->unit_number ?? 'N/A',
            $approval->reservation->contract->project_name ?? 'N/A'
        );

        // Notify marketer
        UserNotification::create([
            'user_id' => $approval->requested_by,
            'message' => $message,
            'event_type' => 'negotiation_deadline_expired',
            'context' => ['reservation_id' => $approval->reservation_id, 'approval_id' => $approval->id],
        ]);
        event(new UserNotificationEvent($approval->requested_by, $message));

        // Notify managers with approve permission
        $managers = User::permission('sales.negotiation.approve')->get();
        foreach ($managers as $manager) {
            UserNotification::create([
                'user_id' => $manager->id,
                'message' => $message,
                'event_type' => 'negotiation_deadline_expired',
                'context' => ['reservation_id' => $approval->reservation_id, 'approval_id' => $approval->id],
            ]);
            event(new UserNotificationEvent($manager->id, $message));
        }

        // Notify credit department (انتهاء مهلة أي إجراء)
        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => 'negotiation_deadline_expired',
                'context' => ['reservation_id' => $approval->reservation_id, 'approval_id' => $approval->id],
            ]);
            event(new UserNotificationEvent($user->id, $message));
        }
    }
}

