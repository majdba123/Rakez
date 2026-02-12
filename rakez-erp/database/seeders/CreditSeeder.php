<?php

namespace Database\Seeders;

use App\Models\ClaimFile;
use App\Models\CreditFinancingTracker;
use App\Models\SalesReservation;
use App\Models\TitleTransfer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreditSeeder extends Seeder
{
    public function run(): void
    {
        $confirmed = SalesReservation::where('status', 'confirmed')->pluck('id')->all();
        shuffle($confirmed);

        $pendingCount = (int) round(count($confirmed) * 0.35);
        $inProgressCount = (int) round(count($confirmed) * 0.25);
        $titleTransferCount = (int) round(count($confirmed) * 0.15);
        $soldCount = (int) round(count($confirmed) * 0.15);
        $rejectedCount = (int) round(count($confirmed) * 0.05);
        $cancelledCount = max(0, count($confirmed) - $pendingCount - $inProgressCount - $titleTransferCount - $soldCount - $rejectedCount);

        $pendingIds = array_slice($confirmed, 0, $pendingCount);
        $inProgressIds = array_slice($confirmed, $pendingCount, $inProgressCount);
        $titleTransferIds = array_slice($confirmed, $pendingCount + $inProgressCount, $titleTransferCount);
        $soldIds = array_slice($confirmed, $pendingCount + $inProgressCount + $titleTransferCount, $soldCount);
        $rejectedIds = array_slice($confirmed, $pendingCount + $inProgressCount + $titleTransferCount + $soldCount, $rejectedCount);
        $cancelledIds = array_slice($confirmed, $pendingCount + $inProgressCount + $titleTransferCount + $soldCount + $rejectedCount, $cancelledCount);

        SalesReservation::whereIn('id', $pendingIds)->update(['credit_status' => 'pending']);
        SalesReservation::whereIn('id', $inProgressIds)->update(['credit_status' => 'in_progress']);
        SalesReservation::whereIn('id', $titleTransferIds)->update(['credit_status' => 'title_transfer']);
        SalesReservation::whereIn('id', $soldIds)->update(['credit_status' => 'sold']);
        SalesReservation::whereIn('id', $rejectedIds)->update(['credit_status' => 'rejected']);
        // Note: cancelledIds use 'rejected' for credit_status since 'cancelled' is not a valid credit_status enum value
        // but they will have 'cancelled' overall_status in CreditFinancingTracker
        SalesReservation::whereIn('id', $cancelledIds)->update(['credit_status' => 'rejected']);

        $creditUsers = User::where('type', 'credit')->pluck('id')->all();
        $admins = User::where('type', 'admin')->pluck('id')->all();
        $creditPool = $creditUsers ?: $admins;

        $allTrackerIds = array_merge($inProgressIds, $titleTransferIds, $soldIds, $rejectedIds, $cancelledIds);
        $stageStatuses = ['pending', 'in_progress', 'completed', 'overdue'];
        
        foreach ($allTrackerIds as $index => $reservationId) {
            if (in_array($reservationId, $soldIds, true)) {
                $overallStatus = 'completed';
            } elseif (in_array($reservationId, $rejectedIds, true)) {
                $overallStatus = 'rejected';
            } elseif (in_array($reservationId, $cancelledIds, true)) {
                $overallStatus = 'cancelled';
            } else {
                $overallStatus = 'in_progress';
            }
            
            // Distribute stage statuses to show variety
            $stage1Status = $overallStatus === 'completed' ? 'completed' : ($overallStatus === 'rejected' ? 'overdue' : $stageStatuses[$index % 4]);
            $stage2Status = $overallStatus === 'completed' ? 'completed' : ($overallStatus === 'rejected' ? 'overdue' : $stageStatuses[($index + 1) % 4]);
            $stage3Status = $overallStatus === 'completed' ? 'completed' : ($overallStatus === 'rejected' ? 'overdue' : $stageStatuses[($index + 2) % 4]);
            $stage4Status = $overallStatus === 'completed' ? 'completed' : ($overallStatus === 'rejected' ? 'overdue' : $stageStatuses[($index + 3) % 4]);
            $stage5Status = $overallStatus === 'completed' ? 'completed' : ($overallStatus === 'rejected' ? 'overdue' : $stageStatuses[($index + 4) % 4]);
            
            CreditFinancingTracker::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'assigned_to' => Arr::random($creditPool),
                    'overall_status' => $overallStatus,
                    'stage_1_status' => $stage1Status,
                    'stage_2_status' => $stage2Status,
                    'stage_3_status' => $stage3Status,
                    'stage_4_status' => $stage4Status,
                    'stage_5_status' => $stage5Status,
                    'completed_at' => $overallStatus === 'completed' ? now()->subDays(1) : null,
                    'rejection_reason' => $overallStatus === 'rejected' ? 'Rejected during credit processing' : null,
                ]
            );
        }

        $titleTransferReservations = array_merge($titleTransferIds, $soldIds);
        $titleTransferStatuses = ['pending', 'preparation', 'scheduled', 'completed'];
        
        foreach ($titleTransferReservations as $index => $reservationId) {
            $isSold = in_array($reservationId, $soldIds, true);
            $status = $isSold ? 'completed' : $titleTransferStatuses[$index % 4];
            
            TitleTransfer::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'processed_by' => Arr::random($creditPool),
                    'status' => $status,
                    'scheduled_date' => in_array($status, ['scheduled', 'completed']) ? now()->addDays(fake()->numberBetween(3, 14))->format('Y-m-d') : null,
                    'completed_date' => $status === 'completed' ? now()->subDays(1)->format('Y-m-d') : null,
                    'notes' => $status === 'completed' ? 'Completed title transfer' : ($status === 'preparation' ? 'In preparation phase' : null),
                ]
            );
        }

        foreach ($soldIds as $reservationId) {
            ClaimFile::firstOrCreate(
                ['sales_reservation_id' => $reservationId],
                [
                    'generated_by' => Arr::random($creditPool),
                    'pdf_path' => 'claims/' . Str::uuid() . '.pdf',
                    'file_data' => [
                        'generated_at' => now()->toDateTimeString(),
                        'notes' => 'Seeded claim file',
                    ],
                ]
            );
        }
    }
}
