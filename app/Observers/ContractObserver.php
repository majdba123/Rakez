<?php

namespace App\Observers;

use App\Jobs\IndexRecordSummaryJob;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

class ContractObserver
{
    public function created(Contract $contract): void
    {
        $this->scheduleIndex($contract->id, false);
    }

    public function updated(Contract $contract): void
    {
        $this->scheduleIndex($contract->id, false);
    }

    public function deleted(Contract $contract): void
    {
        $this->scheduleIndex($contract->id, true);
    }

    private function scheduleIndex(int $id, bool $isDeleted): void
    {
        DB::afterCommit(fn () => IndexRecordSummaryJob::dispatch('contract', $id, $isDeleted));
    }
}
