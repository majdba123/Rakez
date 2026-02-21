<?php

namespace App\Services\Sales;

use App\Models\ReservationPaymentInstallment;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentPlanService
{
    /**
     * Create a payment plan for a reservation.
     */
    public function createPlan(int $reservationId, array $installments, User $user): Collection
    {
        $reservation = SalesReservation::with('contract')->findOrFail($reservationId);

        // Validate: reservation must be for off-plan project
        if (!$reservation->contract || !$reservation->contract->is_off_plan) {
            throw new Exception('Payment plans can only be created for off-plan projects');
        }

        // Validate: reservation must be confirmed with non-refundable down payment
        if ($reservation->status !== 'confirmed') {
            throw new Exception('Payment plans can only be created for confirmed reservations');
        }

        if ($reservation->down_payment_status !== 'non_refundable') {
            throw new Exception('Payment plans can only be created when down payment is non-refundable');
        }

        // Validate: no existing payment plan
        if ($reservation->hasPaymentPlan()) {
            throw new Exception('A payment plan already exists for this reservation');
        }

        DB::beginTransaction();
        try {
            $created = [];

            foreach ($installments as $index => $installment) {
                $created[] = ReservationPaymentInstallment::create([
                    'sales_reservation_id' => $reservationId,
                    'due_date' => $installment['due_date'],
                    'amount' => $installment['amount'],
                    'description' => $installment['description'] ?? 'الدفعة ' . ($index + 1),
                    'status' => 'pending',
                ]);
            }

            DB::commit();

            return collect($created);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a payment installment.
     */
    public function updateInstallment(int $installmentId, array $data, User $user): ReservationPaymentInstallment
    {
        $installment = ReservationPaymentInstallment::findOrFail($installmentId);

        // Only allow updating pending installments
        if ($installment->status === 'paid') {
            throw new Exception('Cannot update a paid installment');
        }

        $installment->update([
            'due_date' => $data['due_date'] ?? $installment->due_date,
            'amount' => $data['amount'] ?? $installment->amount,
            'description' => $data['description'] ?? $installment->description,
            'status' => $data['status'] ?? $installment->status,
        ]);

        return $installment->fresh();
    }

    /**
     * Delete a payment installment.
     */
    public function deleteInstallment(int $installmentId, User $user): bool
    {
        $installment = ReservationPaymentInstallment::findOrFail($installmentId);

        // Only allow deleting pending installments
        if ($installment->status === 'paid') {
            throw new Exception('Cannot delete a paid installment');
        }

        return $installment->delete();
    }

    /**
     * Get payment plan for a reservation.
     */
    public function getPaymentPlan(int $reservationId): Collection
    {
        return ReservationPaymentInstallment::where('sales_reservation_id', $reservationId)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Mark overdue installments.
     * Called by scheduled command.
     */
    public function markOverdueInstallments(): int
    {
        return ReservationPaymentInstallment::pending()
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    /**
     * Get summary of a payment plan.
     */
    public function getPaymentPlanSummary(int $reservationId): array
    {
        $installments = $this->getPaymentPlan($reservationId);

        return [
            'total_installments' => $installments->count(),
            'total_amount' => $installments->sum('amount'),
            'paid_amount' => $installments->where('status', 'paid')->sum('amount'),
            'pending_amount' => $installments->where('status', 'pending')->sum('amount'),
            'overdue_amount' => $installments->where('status', 'overdue')->sum('amount'),
            'paid_count' => $installments->where('status', 'paid')->count(),
            'pending_count' => $installments->where('status', 'pending')->count(),
            'overdue_count' => $installments->where('status', 'overdue')->count(),
            'next_due_date' => $installments->where('status', 'pending')->first()?->due_date,
        ];
    }
}

