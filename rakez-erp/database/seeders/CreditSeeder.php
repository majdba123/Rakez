<?php

namespace Database\Seeders;

use App\Models\ClaimFile;
use App\Models\CreditFinancingTracker;
use App\Models\SalesReservation;
use App\Models\TitleTransfer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class CreditSeeder extends Seeder
{
    public function run(): void
    {
        $confirmed = SalesReservation::where('status', 'confirmed')->get();
        if ($confirmed->isEmpty()) {
            return;
        }

<<<<<<< HEAD
<<<<<<< HEAD
        // Only bank-financing reservations get credit workflow (tracker, title transfer).
        $bankConfirmed = $confirmed->filter(fn ($r) => in_array($r->purchase_mechanism, ['supported_bank', 'unsupported_bank'], true));
        $bankIds = $bankConfirmed->pluck('id')->all();
        if (empty($bankIds)) {
            return;
        }
        shuffle($bankIds);

        $total = count($bankIds);
        $pendingCount = max(1, (int) round($total * 0.30));   // no tracker yet – test "نقل للمرحلة التالية" = initialize
        $inProgressCount = max(1, (int) round($total * 0.30)); // has tracker, stages in progress
        $titleTransferCount = max(0, (int) round($total * 0.15));
        $soldCount = max(0, (int) round($total * 0.15));
        $rejectedCount = max(0, (int) round($total * 0.10));
        $remaining = $total - $pendingCount - $inProgressCount - $titleTransferCount - $soldCount - $rejectedCount;
        $pendingCount += max(0, $remaining);

        $pendingIds = array_slice($bankIds, 0, $pendingCount);
        $inProgressIds = array_slice($bankIds, $pendingCount, $inProgressCount);
        $titleTransferIds = array_slice($bankIds, $pendingCount + $inProgressCount, $titleTransferCount);
        $soldIds = array_slice($bankIds, $pendingCount + $inProgressCount + $titleTransferCount, $soldCount);
        $rejectedIds = array_slice($bankIds, $pendingCount + $inProgressCount + $titleTransferCount + $soldCount, $rejectedCount);
=======
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
        $pendingCount = (int) round(count($confirmed) * 0.4);
        $inProgressCount = (int) round(count($confirmed) * 0.3);
        $titleTransferCount = (int) round(count($confirmed) * 0.2);
        $soldCount = max(0, count($confirmed) - $pendingCount - $inProgressCount - $titleTransferCount);

        $pendingIds = array_slice($confirmed, 0, $pendingCount);
        $inProgressIds = array_slice($confirmed, $pendingCount, $inProgressCount);
        $titleTransferIds = array_slice($confirmed, $pendingCount + $inProgressCount, $titleTransferCount);
        $soldIds = array_slice($confirmed, $pendingCount + $inProgressCount + $titleTransferCount, $soldCount);
<<<<<<< HEAD
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)

        SalesReservation::whereIn('id', $pendingIds)->update(['credit_status' => 'pending']);
        SalesReservation::whereIn('id', $inProgressIds)->update(['credit_status' => 'in_progress']);
        SalesReservation::whereIn('id', $titleTransferIds)->update(['credit_status' => 'title_transfer']);
        SalesReservation::whereIn('id', $soldIds)->update(['credit_status' => 'sold']);
<<<<<<< HEAD
<<<<<<< HEAD
        // Rejected by bank: set down_payment_confirmed so dashboard "rejected with paid down payment" is testable
        SalesReservation::whereIn('id', $rejectedIds)->update([
            'credit_status' => 'rejected',
            'down_payment_confirmed' => true,
            'down_payment_confirmed_at' => now()->subDays(5),
        ]);

        // Cash confirmed: keep credit_status pending (no tracker ever).
        $cashConfirmed = $confirmed->filter(fn ($r) => $r->purchase_mechanism === 'cash');
        SalesReservation::whereIn('id', $cashConfirmed->pluck('id')->all())->update(['credit_status' => 'pending']);
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)

        $creditUsers = User::where('type', 'credit')->pluck('id')->all();
        $admins = User::where('type', 'admin')->pluck('id')->all();
        $creditPool = $creditUsers ?: $admins;
        if (empty($creditPool)) {
            $creditPool = User::limit(1)->pluck('id')->all();
        }

<<<<<<< HEAD
<<<<<<< HEAD
        $reservationsWithTrackers = array_merge($inProgressIds, $titleTransferIds, $soldIds, $rejectedIds);

        foreach ($reservationsWithTrackers as $index => $reservationId) {
            $reservation = SalesReservation::find($reservationId);
            if (!$reservation) {
                continue;
            }

            $isSupportedBank = $reservation->purchase_mechanism === 'supported_bank';
            $isSold = in_array($reservationId, $soldIds, true);
            $isRejected = in_array($reservationId, $rejectedIds, true);
            $isTitleTransfer = in_array($reservationId, $titleTransferIds, true);

            if ($isSold) {
                $overallStatus = 'completed';
                $stageStatuses = $this->allStagesCompleted();
                $deadlines = $this->completedTrackerDeadlines();
            } elseif ($isRejected) {
                $overallStatus = 'rejected';
                $stageStatuses = $this->rejectedStages();
                $deadlines = $this->rejectedDeadlines();
            } elseif ($isTitleTransfer) {
                $overallStatus = 'completed';
                $stageStatuses = $this->allStagesCompleted();
                $deadlines = $this->completedTrackerDeadlines();
            } else {
                $overallStatus = 'in_progress';
                $currentStage = ($index % 5) + 1;
                $stageStatuses = $this->inProgressStages($currentStage);
                $deadlines = $this->inProgressDeadlines($currentStage, $isSupportedBank);
            }

            CreditFinancingTracker::updateOrCreate(
                ['sales_reservation_id' => $reservationId],
                array_merge(
                    [
                        'assigned_to' => Arr::random($creditPool),
                        'overall_status' => $overallStatus,
                        'is_supported_bank' => $isSupportedBank,
                        'bank_name' => $stageStatuses['bank_name'] ?? ($overallStatus !== 'rejected' ? 'بنك الراجحي' : null),
                        'client_salary' => $overallStatus === 'rejected' ? null : fake()->numberBetween(12000, 45000),
                        'employment_type' => $overallStatus === 'rejected' ? null : Arr::random(['government', 'private']),
                        'appraiser_name' => ($overallStatus === 'completed' || $overallStatus === 'in_progress') ? fake()->name() : null,
                        'rejection_reason' => $isRejected ? 'تم رفض طلب التمويل من البنك' : null,
                        'completed_at' => $overallStatus === 'completed' ? now()->subDays(fake()->numberBetween(1, 14)) : null,
                    ],
                    $stageStatuses,
                    $deadlines
                )
            );
        }

        $titleTransferReservations = array_merge($titleTransferIds, $soldIds);
        foreach ($titleTransferReservations as $index => $reservationId) {
            $isSold = in_array($reservationId, $soldIds, true);
            $status = $isSold ? 'completed' : ['pending', 'preparation', 'scheduled'][$index % 3];
            $scheduledDate = in_array($status, ['scheduled', 'completed']) ? now()->addDays(fake()->numberBetween(3, 14)) : null;
            $completedDate = $isSold ? now()->subDays(fake()->numberBetween(1, 10)) : null;

            TitleTransfer::updateOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'processed_by' => Arr::random($creditPool),
                    'status' => $status,
                    'scheduled_date' => $scheduledDate,
                    'completed_date' => $completedDate,
                    'notes' => $isSold ? 'تم إكمال نقل الملكية' : ($status === 'preparation' ? 'فترة التجهيز قبل الإفراغ' : null),
=======
        foreach (array_merge($inProgressIds, $titleTransferIds, $soldIds) as $reservationId) {
            $status = in_array($reservationId, $soldIds, true) ? 'completed' : 'in_progress';
            CreditFinancingTracker::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'assigned_to' => Arr::random($creditPool),
                    'overall_status' => $status,
                    'stage_1_status' => $status === 'completed' ? 'completed' : 'in_progress',
                    'stage_2_status' => $status === 'completed' ? 'completed' : 'pending',
                    'stage_3_status' => $status === 'completed' ? 'completed' : 'pending',
                    'stage_4_status' => $status === 'completed' ? 'completed' : 'pending',
                    'stage_5_status' => $status === 'completed' ? 'completed' : 'pending',
                    'completed_at' => $status === 'completed' ? now()->subDays(1) : null,
                ]
            );
        }

        foreach (array_merge($titleTransferIds, $soldIds) as $reservationId) {
            $isSold = in_array($reservationId, $soldIds, true);
            TitleTransfer::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'processed_by' => Arr::random($creditPool),
=======
        foreach (array_merge($inProgressIds, $titleTransferIds, $soldIds) as $reservationId) {
            $status = in_array($reservationId, $soldIds, true) ? 'completed' : 'in_progress';
            CreditFinancingTracker::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'assigned_to' => Arr::random($creditPool),
                    'overall_status' => $status,
                    'stage_1_status' => $status === 'completed' ? 'completed' : 'in_progress',
                    'stage_2_status' => $status === 'completed' ? 'completed' : 'pending',
                    'stage_3_status' => $status === 'completed' ? 'completed' : 'pending',
                    'stage_4_status' => $status === 'completed' ? 'completed' : 'pending',
                    'stage_5_status' => $status === 'completed' ? 'completed' : 'pending',
                    'completed_at' => $status === 'completed' ? now()->subDays(1) : null,
                ]
            );
        }

        foreach (array_merge($titleTransferIds, $soldIds) as $reservationId) {
            $isSold = in_array($reservationId, $soldIds, true);
            TitleTransfer::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'processed_by' => Arr::random($creditPool),
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
                    'status' => $isSold ? 'completed' : 'scheduled',
                    'scheduled_date' => now()->addDays(fake()->numberBetween(3, 14))->format('Y-m-d'),
                    'completed_date' => $isSold ? now()->subDays(1)->format('Y-m-d') : null,
                    'notes' => $isSold ? 'Completed title transfer' : null,
<<<<<<< HEAD
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
=======
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
                ]
            );
        }

        foreach ($soldIds as $reservationId) {
            $reservation = SalesReservation::with(['contract.info', 'contractUnit', 'marketingEmployee.team', 'titleTransfer'])
                ->find($reservationId);
            if (!$reservation) {
                continue;
            }

            $contract = $reservation->contract;
            $unit = $reservation->contractUnit;
            $info = $contract?->info;

            $fileData = [
                'project_name' => $contract?->project_name ?? $info?->project_name ?? 'مشروع بذور',
                'project_location' => $contract?->city ?? null,
                'project_district' => $contract?->district ?? null,
                'unit_number' => $unit?->unit_number ?? null,
                'unit_type' => $unit?->unit_type ?? null,
                'unit_area' => $unit?->area ?? null,
                'unit_price' => $unit?->price ?? null,
                'client_name' => $reservation->client_name,
                'client_mobile' => $reservation->client_mobile,
                'client_nationality' => $reservation->client_nationality,
                'client_iban' => $reservation->client_iban,
                'down_payment_amount' => $reservation->down_payment_amount,
                'down_payment_status' => $reservation->down_payment_status,
                'payment_method' => $reservation->payment_method,
                'purchase_mechanism' => $reservation->purchase_mechanism,
                'brokerage_commission_percent' => $reservation->brokerage_commission_percent,
                'commission_payer' => $reservation->commission_payer,
                'tax_amount' => $reservation->tax_amount,
                'team_name' => $reservation->marketingEmployee?->team?->name,
                'marketer_name' => $reservation->marketingEmployee?->name,
                'contract_date' => $reservation->contract_date?->format('Y-m-d'),
                'confirmed_at' => $reservation->confirmed_at?->format('Y-m-d H:i:s'),
                'title_transfer_date' => $reservation->titleTransfer?->completed_date?->format('Y-m-d'),
                'reservation_id' => $reservation->id,
                'reservation_type' => $reservation->reservation_type,
                'generated_at' => now()->toDateTimeString(),
                'notes' => 'ملف مطالبة من البذور',
            ];

            ClaimFile::updateOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'generated_by' => Arr::random($creditPool),
                    'pdf_path' => null,
                    'file_data' => $fileData,
                ]
            );
        }
    }

    private function allStagesCompleted(): array
    {
        $now = now();
        return [
            'stage_1_status' => 'completed',
            'stage_1_completed_at' => $now->copy()->subDays(18),
            'stage_1_deadline' => $now->copy()->subDays(20),
            'stage_2_status' => 'completed',
            'stage_2_completed_at' => $now->copy()->subDays(13),
            'stage_2_deadline' => $now->copy()->subDays(15),
            'stage_3_status' => 'completed',
            'stage_3_completed_at' => $now->copy()->subDays(10),
            'stage_3_deadline' => $now->copy()->subDays(12),
            'stage_4_status' => 'completed',
            'stage_4_completed_at' => $now->copy()->subDays(7),
            'stage_4_deadline' => $now->copy()->subDays(9),
            'stage_5_status' => 'completed',
            'stage_5_completed_at' => $now->copy()->subDays(2),
            'stage_5_deadline' => $now->copy()->subDays(4),
        ];
    }

    private function completedTrackerDeadlines(): array
    {
        return [];
    }

    private function rejectedStages(): array
    {
        return [
            'stage_1_status' => 'completed',
            'stage_2_status' => 'overdue',
            'stage_3_status' => 'pending',
            'stage_4_status' => 'pending',
            'stage_5_status' => 'pending',
        ];
    }

    private function rejectedDeadlines(): array
    {
        $now = now();
        return [
            'stage_1_deadline' => $now->copy()->subDays(1),
            'stage_2_deadline' => $now->copy()->subDays(2),
        ];
    }

    /**
     * Sequential stages: 1..(current-1) completed, current in_progress, rest pending.
     */
    private function inProgressStages(int $currentStage): array
    {
        $now = now();
        $out = ['bank_name' => 'بنك الأهلي'];
        for ($i = 1; $i <= 5; $i++) {
            if ($i < $currentStage) {
                $out["stage_{$i}_status"] = 'completed';
                $out["stage_{$i}_completed_at"] = $now->copy()->subDays(20 - $i * 3);
                $out["stage_{$i}_deadline"] = $now->copy()->subDays(21 - $i * 3);
            } elseif ($i === $currentStage) {
                $out["stage_{$i}_status"] = 'in_progress';
            } else {
                $out["stage_{$i}_status"] = 'pending';
            }
        }
        return $out;
    }

    private function inProgressDeadlines(int $currentStage, bool $isSupportedBank): array
    {
        $now = now();
        $out = [];
        $extraHours = $isSupportedBank ? (5 * 24) : 0;
        $deadlines = [1 => 48, 2 => 120, 3 => 72, 4 => 48, 5 => 120];
        for ($i = 1; $i <= 5; $i++) {
            $hours = $deadlines[$i] + ($i === 5 ? $extraHours : 0);
            $out["stage_{$i}_deadline"] = $now->copy()->addHours($hours);
        }
        return $out;
    }
}
