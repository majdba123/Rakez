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

        $pendingCount = (int) round(count($confirmed) * 0.4);
        $inProgressCount = (int) round(count($confirmed) * 0.3);
        $titleTransferCount = (int) round(count($confirmed) * 0.2);
        $soldCount = max(0, count($confirmed) - $pendingCount - $inProgressCount - $titleTransferCount);

        $pendingIds = array_slice($confirmed, 0, $pendingCount);
        $inProgressIds = array_slice($confirmed, $pendingCount, $inProgressCount);
        $titleTransferIds = array_slice($confirmed, $pendingCount + $inProgressCount, $titleTransferCount);
        $soldIds = array_slice($confirmed, $pendingCount + $inProgressCount + $titleTransferCount, $soldCount);

        SalesReservation::whereIn('id', $pendingIds)->update(['credit_status' => 'pending']);
        SalesReservation::whereIn('id', $inProgressIds)->update(['credit_status' => 'in_progress']);
        SalesReservation::whereIn('id', $titleTransferIds)->update(['credit_status' => 'title_transfer']);
        SalesReservation::whereIn('id', $soldIds)->update(['credit_status' => 'sold']);

        $creditUsers = User::where('type', 'credit')->pluck('id')->all();
        $admins = User::where('type', 'admin')->pluck('id')->all();
        $creditPool = $creditUsers ?: $admins;

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
                    'status' => $isSold ? 'completed' : 'scheduled',
                    'scheduled_date' => now()->addDays(fake()->numberBetween(3, 14))->format('Y-m-d'),
                    'completed_date' => $isSold ? now()->subDays(1)->format('Y-m-d') : null,
                    'notes' => $isSold ? 'Completed title transfer' : null,
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
