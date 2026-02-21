<?php

namespace App\Observers;

use App\Jobs\IndexRecordSummaryJob;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        $this->scheduleIndex($lead->id, false);
    }

    public function updated(Lead $lead): void
    {
        $this->scheduleIndex($lead->id, false);
    }

    public function deleted(Lead $lead): void
    {
        $this->scheduleIndex($lead->id, true);
    }

    private function scheduleIndex(int $id, bool $isDeleted): void
    {
        DB::afterCommit(fn () => IndexRecordSummaryJob::dispatch('lead', $id, $isDeleted));
    }
}
