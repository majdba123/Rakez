<?php

namespace App\Console\Commands;

use App\Jobs\IndexRecordSummaryJob;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\MarketingTask;
use Illuminate\Console\Command;

class AiDailyReindexCommand extends Command
{
    protected $signature = 'ai:daily-reindex';
    protected $description = 'Nightly reconciliation: ensure all record summaries and documents are indexed (hash changed or missing).';

    public function handle(): int
    {
        $count = 0;

        foreach (Lead::query()->pluck('id') as $id) {
            IndexRecordSummaryJob::dispatch('lead', $id, false);
            $count++;
        }
        foreach (Contract::query()->pluck('id') as $id) {
            IndexRecordSummaryJob::dispatch('contract', $id, false);
            $count++;
        }
        foreach (MarketingTask::query()->pluck('id') as $id) {
            IndexRecordSummaryJob::dispatch('marketing_task', $id, false);
            $count++;
        }

        $this->info("Dispatched {$count} record summary jobs. Documents (type=document) are not re-scanned here; use IngestDocumentJob when uploading.");
        return self::SUCCESS;
    }
}
