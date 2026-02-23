<?php

namespace Database\Seeders;

use App\Models\NegotiationApproval;
use App\Models\ReservationPaymentInstallment;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class NegotiationsPaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();

        $underNegotiation = SalesReservation::where('status', 'under_negotiation')->pluck('id')->all();
        $targetApprovals = min($counts['negotiation_approvals'], count($underNegotiation));

        $pendingCount = min(50, $targetApprovals);
        $processedCount = max(0, $targetApprovals - $pendingCount);
        $expiredCount = min(20, $targetApprovals - $pendingCount);
        $remainingProcessed = max(0, $processedCount - $expiredCount);
        $approvalStatuses = array_merge(
            array_fill(0, $pendingCount, 'pending'),
            array_fill(0, (int) ceil($remainingProcessed / 2), 'approved'),
            array_fill(0, (int) floor($remainingProcessed / 2), 'rejected'),
            array_fill(0, $expiredCount, 'expired')
        );
        shuffle($approvalStatuses);

        $approvalReservations = array_slice($underNegotiation, 0, $targetApprovals);
        $salesLeaders = User::where('type', 'sales')->where('is_manager', true)->pluck('id')->all();
        $admins = User::where('type', 'admin')->pluck('id')->all();
        $leaderPool = $salesLeaders ?: $admins;

        foreach ($approvalReservations as $index => $reservationId) {
            $status = $approvalStatuses[$index] ?? 'pending';
            $reservation = SalesReservation::find($reservationId);
            $originalPrice = $reservation?->contractUnit?->price ?? fake()->randomFloat(2, 300000, 900000);
            $proposedPrice = $originalPrice - fake()->randomFloat(2, 5000, 50000);

            $deadlineAt = $status === 'expired' 
                ? now()->subDays(1) 
                : now()->addHours(48);
            
            NegotiationApproval::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'requested_by' => $reservation?->marketing_employee_id ?? Arr::random(User::where('type', 'sales')->pluck('id')->all()),
                    'approved_by' => in_array($status, ['pending', 'expired']) ? null : Arr::random($leaderPool),
                    'status' => $status,
                    'negotiation_reason' => 'price',
                    'original_price' => $originalPrice,
                    'proposed_price' => $proposedPrice,
                    'manager_notes' => $status === 'rejected' ? 'Rejected by manager' : ($status === 'expired' ? 'Expired without response' : null),
                    'deadline_at' => $deadlineAt,
                    'responded_at' => in_array($status, ['pending', 'expired']) ? null : now()->subHours(6),
                ]
            );
        }

        $offPlanConfirmed = SalesReservation::where('status', 'confirmed')
            ->whereHas('contract', function ($query) {
                $query->where('is_off_plan', true);
            })
            ->pluck('id')
            ->all();

        foreach ($offPlanConfirmed as $reservationId) {
            for ($i = 0; $i < $counts['installments_per_offplan_confirmed']; $i++) {
                ReservationPaymentInstallment::create([
                    'sales_reservation_id' => $reservationId,
                    'due_date' => now()->addMonths($i + 1)->format('Y-m-d'),
                    'amount' => fake()->randomFloat(2, 20000, 120000),
                    'description' => 'Installment ' . ($i + 1),
                    'status' => $i === 0 ? 'paid' : ($i === 1 ? 'overdue' : 'pending'),
                ]);
            }
        }
    }
}
