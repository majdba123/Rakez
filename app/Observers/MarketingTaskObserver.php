<?php

namespace App\Observers;

use App\Jobs\IndexRecordSummaryJob;
use App\Models\MarketingTask;
use Illuminate\Support\Facades\DB;

class MarketingTaskObserver
{
    public function created(MarketingTask $task): void
    {
        $this->scheduleIndex($task->id, false);
    }

    public function updated(MarketingTask $task): void
    {
        $this->scheduleIndex($task->id, false);
    }

    public function deleted(MarketingTask $task): void
    {
        $this->scheduleIndex($task->id, true);
    }

    private function scheduleIndex(int $id, bool $isDeleted): void
    {
        DB::afterCommit(fn () => IndexRecordSummaryJob::dispatch('marketing_task', $id, $isDeleted));
    }
}
