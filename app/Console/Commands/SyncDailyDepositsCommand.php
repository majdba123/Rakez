<?php

namespace App\Console\Commands;

use App\Constants\DepositStatus;
use App\Models\DailyDeposit;
use App\Models\Deposit;
use Illuminate\Console\Command;

class SyncDailyDepositsCommand extends Command
{
    protected $signature = 'marketing:sync-daily-deposits
                            {--dry-run : List what would be synced without creating records}';
    protected $description = 'Backfill daily_deposits from confirmed accounting deposits (for marketing dashboard KPIs).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $deposits = Deposit::where('status', DepositStatus::CONFIRMED)
            ->whereNotNull('confirmed_at')
            ->whereDoesntHave('dailyDeposit')
            ->get();

        if ($deposits->isEmpty()) {
            $this->info('No confirmed deposits missing a daily_deposit record.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['Deposit ID', 'Date', 'Amount', 'Contract ID', 'Reservation ID'],
                $deposits->map(fn (Deposit $d) => [
                    $d->id,
                    $d->confirmed_at->toDateString(),
                    $d->amount,
                    $d->contract_id,
                    $d->sales_reservation_id,
                ])
            );
            $this->info("Would create {$deposits->count()} daily_deposit record(s). Run without --dry-run to apply.");
            return self::SUCCESS;
        }

        $created = 0;
        foreach ($deposits as $deposit) {
            DailyDeposit::create([
                'deposit_id' => $deposit->id,
                'date' => $deposit->confirmed_at->toDateString(),
                'amount' => $deposit->amount,
                'booking_id' => $deposit->sales_reservation_id ? (string) $deposit->sales_reservation_id : null,
                'project_id' => $deposit->contract_id,
            ]);
            $created++;
        }

        $this->info("Created {$created} daily_deposit record(s).");
        return self::SUCCESS;
    }
}
