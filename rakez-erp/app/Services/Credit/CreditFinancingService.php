<?php

namespace App\Services\Credit;

use App\Models\SalesReservation;
use App\Models\CreditFinancingTracker;
use App\Models\User;
use App\Models\UserNotification;
use App\Events\UserNotificationEvent;
use Illuminate\Support\Facades\DB;
use Exception;

class CreditFinancingService
{
    /**
     * Stage names for reference.
     */
    public const STAGE_NAMES = [
        1 => 'التواصل مع العميل',
        2 => 'رفع الطلب للبنك',
        3 => 'صدور التقييم',
        4 => 'زيارة المقيم',
        5 => 'الإجراءات البنكية والعقود',
    ];

    /**
     * Get tracker by reservation (booking) id. Internal use; used by controller to resolve booking_id to tracker.
     */
    public function getTrackerByReservationId(int $reservationId): CreditFinancingTracker
    {
        return CreditFinancingTracker::where('sales_reservation_id', $reservationId)->firstOrFail();
    }

    /**
     * Initialize a financing tracker for a reservation.
     */
    public function initializeTracker(int $reservationId, int $assignedTo): CreditFinancingTracker
    {
        $reservation = SalesReservation::findOrFail($reservationId);

        // Validate reservation is confirmed and uses bank financing
        if ($reservation->status !== 'confirmed') {
            throw new Exception('يمكن بدء إجراءات التمويل فقط للحجوزات المؤكدة');
        }

        if (!$reservation->isBankFinancing()) {
            throw new Exception('إجراءات التمويل متاحة فقط للشراء بالتمويل البنكي');
        }

        // Check if tracker already exists
        if ($reservation->hasFinancingTracker()) {
            throw new Exception('تم تهيئة إجراءات التمويل مسبقاً لهذا الحجز');
        }

        DB::beginTransaction();
        try {
            $now = now();
            
            // Calculate stage 1 deadline (48 hours)
            $stage1Deadline = $now->copy()->addHours(CreditFinancingTracker::STAGE_DEADLINES[1]);

            $tracker = CreditFinancingTracker::create([
                'sales_reservation_id' => $reservationId,
                'assigned_to' => $assignedTo,
                'stage_1_status' => 'in_progress',
                'stage_1_deadline' => $stage1Deadline,
                'is_supported_bank' => $reservation->isSupportedBank(),
                'overall_status' => 'in_progress',
            ]);

            // Update reservation credit status
            $reservation->update(['credit_status' => 'in_progress']);

            DB::commit();

            return $tracker->fresh(['reservation', 'assignedUser']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Complete a stage in the financing tracker.
     */
    public function completeStage(int $trackerId, int $stage, array $data, User $user): CreditFinancingTracker
    {
        if ($stage < 1 || $stage > 5) {
            throw new Exception('رقم المرحلة غير صالح');
        }

        $tracker = CreditFinancingTracker::findOrFail($trackerId);

        if ($tracker->overall_status !== 'in_progress') {
            throw new Exception('لا يمكن الانتقال للمرحلة التالية حالياً');
        }

        // Ensure previous stages are completed
        for ($i = 1; $i < $stage; $i++) {
            if ($tracker->{"stage_{$i}_status"} !== 'completed') {
                throw new Exception("يجب إكمال المرحلة {$i} أولاً");
            }
        }

        DB::beginTransaction();
        try {
            $now = now();
            $updateData = [
                "stage_{$stage}_status" => 'completed',
                "stage_{$stage}_completed_at" => $now,
            ];

            // Stage-specific data (bank_name optional so "Confirm" transition works; can be set later)
            if ($stage === 1) {
                $updateData['bank_name'] = isset($data['bank_name']) && $data['bank_name'] !== '' ? $data['bank_name'] : null;
                $updateData['client_salary'] = $data['client_salary'] ?? null;
                $updateData['employment_type'] = $data['employment_type'] ?? null;
            }

            if ($stage === 4) {
                $updateData['appraiser_name'] = $data['appraiser_name'] ?? null;
            }

            // Set next stage deadline if not the last stage
            if ($stage < 5) {
                $nextStage = $stage + 1;
                $nextDeadlineHours = CreditFinancingTracker::STAGE_DEADLINES[$nextStage];
                
                // Add extra time for supported banks on stage 5
                if ($nextStage === 5 && $tracker->is_supported_bank) {
                    $nextDeadlineHours += CreditFinancingTracker::SUPPORTED_BANK_EXTRA_DAYS * 24;
                }

                $updateData["stage_{$nextStage}_status"] = 'in_progress';
                $updateData["stage_{$nextStage}_deadline"] = $now->copy()->addHours($nextDeadlineHours);
            }

            // If completing stage 5, mark overall as completed
            if ($stage === 5) {
                $updateData['overall_status'] = 'completed';
                $updateData['completed_at'] = $now;
            }

            $tracker->update($updateData);

            // If completed, update reservation credit status
            if ($stage === 5) {
                $tracker->reservation->update(['credit_status' => 'title_transfer']);
                $this->notifyStageCompletion($tracker, 'تم إكمال جميع إجراءات التمويل');
            }

            DB::commit();

            return $tracker->fresh(['reservation', 'assignedUser']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject financing.
     */
    public function rejectFinancing(int $trackerId, string $reason, User $user): CreditFinancingTracker
    {
        $tracker = CreditFinancingTracker::findOrFail($trackerId);

        if ($tracker->overall_status !== 'in_progress') {
            throw new Exception('لا يمكن رفض طلب التمويل في الحالة الحالية');
        }

        DB::beginTransaction();
        try {
            $tracker->update([
                'overall_status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            $tracker->reservation->update(['credit_status' => 'rejected']);

            // Notify relevant users
            $this->notifyRejection($tracker);

            DB::commit();

            return $tracker->fresh(['reservation']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark overdue stages.
     * Called by scheduled command.
     */
    public function markOverdueStages(): int
    {
        $count = 0;
        $now = now();

        $trackers = CreditFinancingTracker::inProgress()->get();

        foreach ($trackers as $tracker) {
            for ($i = 1; $i <= 5; $i++) {
                $status = $tracker->{"stage_{$i}_status"};
                $deadline = $tracker->{"stage_{$i}_deadline"};

                if (in_array($status, ['pending', 'in_progress']) && $deadline && $now->gt($deadline)) {
                    $tracker->update(["stage_{$i}_status" => 'overdue']);
                    $count++;

                    // Notify about overdue
                    $this->notifyOverdue($tracker, $i);
                }
            }
        }

        return $count;
    }

    /**
     * Advance to next stage, or initialize if no tracker exists.
     * Single action for "نقل للمرحلة التالية": either creates the financing request or completes the current stage.
     *
     * @return array{action: 'initialized'|'stage_completed', financing: CreditFinancingTracker, stage?: int}
     */
    public function advanceOrInitialize(int $reservationId, array $data, User $user): array
    {
        $reservation = SalesReservation::findOrFail($reservationId);

        if (!$reservation->hasFinancingTracker()) {
            $tracker = $this->initializeTracker($reservationId, $user->id);
            return [
                'action' => 'initialized',
                'financing' => $tracker->fresh(['reservation', 'assignedUser']),
            ];
        }

        $tracker = CreditFinancingTracker::where('sales_reservation_id', $reservationId)->first();

        if ($tracker->allStagesCompleted()) {
            throw new Exception('لا يمكن الانتقال للمرحلة التالية حالياً');
        }

        $stage = $tracker->getCurrentStage();
        $tracker = $this->completeStage($tracker->id, $stage, $data, $user);

        return [
            'action' => 'stage_completed',
            'financing' => $tracker,
            'stage' => $stage,
        ];
    }

    /**
     * Get tracker details with full info.
     */
    public function getTrackerDetails(int $trackerId): array
    {
        $tracker = CreditFinancingTracker::with(['reservation.contract', 'reservation.contractUnit', 'assignedUser'])
            ->findOrFail($trackerId);

        return [
            'financing' => $tracker,
            'progress_summary' => $tracker->getProgressSummary(),
            'current_stage' => $tracker->getCurrentStage(),
            'remaining_days' => $tracker->getRemainingDays(),
            'all_completed' => $tracker->allStagesCompleted(),
        ];
    }

    /**
     * Notify about stage completion.
     */
    protected function notifyStageCompletion(CreditFinancingTracker $tracker, string $message): void
    {
        // Notify credit department users
        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message . ' - حجز رقم ' . $tracker->sales_reservation_id,
            ]);
        }
    }

    /**
     * Notify about rejection.
     */
    protected function notifyRejection(CreditFinancingTracker $tracker): void
    {
        // Notify marketer
        $reservation = $tracker->reservation;
        if ($reservation->marketing_employee_id) {
            UserNotification::create([
                'user_id' => $reservation->marketing_employee_id,
                'message' => 'تم رفض طلب التمويل للحجز رقم ' . $reservation->id,
            ]);
        }
    }

    /**
     * Notify about overdue stage.
     */
    protected function notifyOverdue(CreditFinancingTracker $tracker, int $stage): void
    {
        $stageName = self::STAGE_NAMES[$stage] ?? "المرحلة {$stage}";
        $message = "تأخر في مرحلة: {$stageName} - حجز رقم {$tracker->sales_reservation_id}";

        // Notify assigned user (انتهاء مهلة أي إجراء)
        if ($tracker->assigned_to) {
            UserNotification::create([
                'user_id' => $tracker->assigned_to,
                'message' => $message,
                'event_type' => 'financing_stage_overdue',
                'context' => [
                    'reservation_id' => $tracker->sales_reservation_id,
                    'stage' => $stage,
                ],
            ]);
        }

        // Notify credit managers
        $creditManagers = User::where('type', 'credit')->where('is_manager', true)->get();
        foreach ($creditManagers as $manager) {
            UserNotification::create([
                'user_id' => $manager->id,
                'message' => $message,
                'event_type' => 'financing_stage_overdue',
                'context' => [
                    'reservation_id' => $tracker->sales_reservation_id,
                    'stage' => $stage,
                ],
            ]);
        }
    }
}



