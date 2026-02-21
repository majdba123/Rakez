<?php

namespace Tests\Unit\Observers;

use App\Jobs\IndexRecordSummaryJob;
use App\Models\Contract;
use App\Observers\ContractObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContractObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Contract::observe(ContractObserver::class);
    }

    public function test_created_dispatches_index_job(): void
    {
        Queue::fake();

        Contract::factory()->create([
            'project_name' => 'Test Project',
            'status' => 'pending',
        ]);

        Queue::assertPushed(IndexRecordSummaryJob::class);
    }

    public function test_updated_dispatches_index_job(): void
    {
        $contract = Contract::factory()->create(['status' => 'pending']);
        Queue::fake();

        $contract->update(['project_name' => 'Updated Name']);

        Queue::assertPushed(IndexRecordSummaryJob::class);
    }

    public function test_deleted_dispatches_index_job(): void
    {
        $contract = Contract::factory()->create();
        Queue::fake();

        $contract->delete();

        Queue::assertPushed(IndexRecordSummaryJob::class);
    }
}
