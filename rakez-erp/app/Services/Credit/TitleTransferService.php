<?php

namespace App\Services\Credit;

use App\Models\SalesReservation;
use App\Models\TitleTransfer;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Exception;

class TitleTransferService
{
    /**
     * Initialize title transfer for a reservation.
     */
    public function initializeTransfer(int $reservationId, int $processedBy): TitleTransfer
    {
        $reservation = SalesReservation::findOrFail($reservationId);

        // Validate reservation is ready for title transfer
        if ($reservation->status !== 'confirmed') {
            throw new Exception('يمكن بدء نقل الملكية فقط للحجوزات المؤكدة');
        }

        // For bank financing, financing tracker must be completed
        if ($reservation->isBankFinancing()) {
            $tracker = $reservation->financingTracker;
            if (!$tracker || $tracker->overall_status !== 'completed') {
                throw new Exception('يجب إكمال إجراءات التمويل البنكي أولاً');
            }
        }

        // Check if title transfer already exists
        if ($reservation->hasTitleTransfer()) {
            throw new Exception('يوجد طلب نقل ملكية مسبقًا لهذا الحجز');
        }

        DB::beginTransaction();
        try {
            $transfer = TitleTransfer::create([
                'sales_reservation_id' => $reservationId,
                'processed_by' => $processedBy,
                'status' => 'preparation',
            ]);

            $reservation->update(['credit_status' => 'title_transfer']);

            DB::commit();

            return $transfer->fresh(['reservation', 'processedBy']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Schedule a title transfer date.
     */
    public function scheduleTransfer(int $transferId, string $scheduledDate, ?string $notes = null): TitleTransfer
    {
        $transfer = TitleTransfer::findOrFail($transferId);

        if ($transfer->status === 'completed') {
            throw new Exception('نقل الملكية مكتمل مسبقًا');
        }

        DB::beginTransaction();
        try {
            $transfer->update([
                'status' => 'scheduled',
                'scheduled_date' => $scheduledDate,
                'notes' => $notes ?? $transfer->notes,
            ]);

            // Notify relevant parties
            $this->notifyScheduled($transfer);

            DB::commit();

            return $transfer->fresh(['reservation']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel/clear the scheduled evacuation date (إلغاء موعد الافراغ).
     * Only allowed when status is 'scheduled'.
     */
    public function unscheduleTransfer(int $transferId): TitleTransfer
    {
        $transfer = TitleTransfer::findOrFail($transferId);

        if ($transfer->status === 'completed') {
            throw new Exception('نقل الملكية مكتمل ولا يمكن إلغاء الموعد.');
        }

        if ($transfer->status !== 'scheduled') {
            throw new Exception('لا يوجد موعد محدد للإلغاء.');
        }

        DB::beginTransaction();
        try {
            $transfer->update([
                'status' => 'preparation',
                'scheduled_date' => null,
                'notes' => null,
            ]);

            DB::commit();

            return $transfer->fresh(['reservation']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Complete a title transfer.
     */
    public function completeTransfer(int $transferId, User $user): TitleTransfer
    {
        $transfer = TitleTransfer::findOrFail($transferId);

        if ($transfer->status === 'completed') {
            throw new Exception('نقل الملكية مكتمل مسبقًا');
        }

        DB::beginTransaction();
        try {
            $transfer->update([
                'status' => 'completed',
                'completed_date' => now()->toDateString(),
            ]);

            // Update reservation to sold
            $transfer->reservation->update(['credit_status' => 'sold']);

            // Update unit status to sold
            if ($transfer->reservation->contractUnit) {
                $transfer->reservation->contractUnit->update(['status' => 'sold']);
            }

            // Notify relevant parties
            $this->notifyCompleted($transfer);

            DB::commit();

            return $transfer->fresh(['reservation']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get sold projects (completed title transfers) - paginated.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getSoldProjects(array $filters = [], int $perPage = 15)
    {
        $query = TitleTransfer::with(['reservation.contract', 'reservation.contractUnit', 'processedBy'])
            ->completed();

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->whereDate('completed_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('completed_date', '<=', $filters['to_date']);
        }

        // Filter by contract
        if (!empty($filters['contract_id'])) {
            $query->whereHas('reservation', function ($q) use ($filters) {
                $q->where('contract_id', $filters['contract_id']);
            });
        }

        return $query->orderBy('completed_date', 'desc')->paginate($perPage);
    }

    /**
     * Get transfers pending completion.
     */
    public function getPendingTransfers(): Collection
    {
        return TitleTransfer::with(['reservation.contract', 'reservation.contractUnit', 'processedBy'])
            ->whereIn('status', ['pending', 'preparation', 'scheduled'])
            ->orderBy('scheduled_date', 'asc')
            ->get();
    }

    /**
     * Notify about scheduled transfer.
     */
    protected function notifyScheduled(TitleTransfer $transfer): void
    {
        $reservation = $transfer->reservation;
        $message = sprintf(
            'تم جدولة نقل الملكية للحجز رقم %d في تاريخ %s',
            $reservation->id,
            $transfer->scheduled_date
        );

        // Notify marketer
        if ($reservation->marketing_employee_id) {
            UserNotification::create([
                'user_id' => $reservation->marketing_employee_id,
                'message' => $message,
            ]);
        }
    }

    /**
     * Notify about completed transfer.
     */
    protected function notifyCompleted(TitleTransfer $transfer): void
    {
        $reservation = $transfer->reservation;
        $message = sprintf(
            'تم إكمال نقل الملكية للحجز رقم %d',
            $reservation->id
        );

        // Notify marketer
        if ($reservation->marketing_employee_id) {
            UserNotification::create([
                'user_id' => $reservation->marketing_employee_id,
                'message' => $message,
                'event_type' => 'evacuation_complete',
                'context' => ['reservation_id' => $reservation->id],
            ]);
        }

        // Notify credit users (اكتمال الإفراغ)
        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => 'evacuation_complete',
                'context' => ['reservation_id' => $reservation->id],
            ]);
        }

        // Notify accounting
        $accountingUsers = User::where('type', 'accounting')->get();
        foreach ($accountingUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'event_type' => 'evacuation_complete',
                'context' => ['reservation_id' => $reservation->id],
            ]);
        }
    }
}



