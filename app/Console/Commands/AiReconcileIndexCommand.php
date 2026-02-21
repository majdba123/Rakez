<?php

namespace App\Console\Commands;

use App\Jobs\IndexRecordSummaryJob;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\MarketingTask;
use Illuminate\Console\Command;

class AiReconcileIndexCommand extends Command
{
    protected $signature = 'ai:reconcile-index {--minutes=5 : Look back this many minutes}';
    protected $description = 'Reconcile AI index: re-dispatch indexing for recently updated records (run every 5 min).';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $since = now()->subMinutes($minutes);

        $count = 0;

        Lead::query()->where('updated_at', '>=', $since)->pluck('id')->each(function (int $id) use (&$count) {
            IndexRecordSummaryJob::dispatch('lead', $id, false);
            $count++;
        });
        Contract::query()->where('updated_at', '>=', $since)->pluck('id')->each(function (int $id) use (&$count) {
            IndexRecordSummaryJob::dispatch('contract', $id, false);
            $count++;
        });
        MarketingTask::query()->where('updated_at', '>=', $since)->pluck('id')->each(function (int $id) use (&$count) {
            IndexRecordSummaryJob::dispatch('marketing_task', $id, false);
            $count++;
        });

        $this->info("Dispatched {$count} index jobs for records updated in the last {$minutes} minutes.");
        return self::SUCCESS;
    }
}
