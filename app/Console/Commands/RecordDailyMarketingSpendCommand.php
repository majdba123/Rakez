<?php

namespace App\Console\Commands;

use App\Models\DailyMarketingSpend;
use Illuminate\Console\Command;

class RecordDailyMarketingSpendCommand extends Command
{
    protected $signature = 'marketing:record-daily-spend
                            {date : Date (Y-m-d) for the spend}
                            {amount : Amount spent}';
    protected $description = 'Record a daily marketing spend entry (for dashboard deposit cost KPI: total daily spend ÷ daily deposits).';

    public function handle(): int
    {
        $date = $this->argument('date');
        $amount = (float) $this->argument('amount');

        if ($amount < 0) {
            $this->error('Amount must be non-negative.');
            return self::FAILURE;
        }

        $parsed = \Carbon\Carbon::parse($date);
        if (! $parsed->isValid()) {
            $this->error('Invalid date. Use Y-m-d.');
            return self::FAILURE;
        }

        DailyMarketingSpend::create([
            'date' => $parsed->toDateString(),
            'amount' => $amount,
        ]);

        $this->info("Recorded daily marketing spend: {$amount} for {$parsed->toDateString()}.");
        return self::SUCCESS;
    }
}
