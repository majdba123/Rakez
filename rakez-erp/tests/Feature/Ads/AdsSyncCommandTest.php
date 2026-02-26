<?php

namespace Tests\Feature\Ads;

use App\Jobs\Ads\PublishOutcomeEventsJob;
use App\Jobs\Ads\SyncCampaignStructureJob;
use App\Jobs\Ads\SyncInsightsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;

class AdsSyncCommandTest extends TestCase
{
    use RefreshDatabase, TestsWithAds;

    public function test_sync_campaigns_dispatches_jobs_for_active_accounts(): void
    {
        Queue::fake();
        $this->createPlatformAccount('meta', 'act_111');
        $this->createPlatformAccount('snap', 'snap_222');

        $this->artisan('ads:sync', ['action' => 'sync-campaigns'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncCampaignStructureJob::class, 2);
    }

    public function test_sync_campaigns_filters_by_platform(): void
    {
        Queue::fake();
        $this->createPlatformAccount('meta', 'act_111');
        $this->createPlatformAccount('snap', 'snap_222');

        $this->artisan('ads:sync', ['action' => 'sync-campaigns', '--platform' => 'meta'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncCampaignStructureJob::class, 1);
        Queue::assertPushed(SyncCampaignStructureJob::class, function ($job) {
            return $job->platform === 'meta';
        });
    }

    public function test_sync_insights_dispatches_jobs(): void
    {
        Queue::fake();
        $this->createPlatformAccount('meta', 'act_111');

        $this->artisan('ads:sync', ['action' => 'sync-insights', '--days' => '14'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncInsightsJob::class, 1);
    }

    public function test_publish_outcomes_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('ads:sync', ['action' => 'publish-outcomes'])
            ->assertExitCode(0);

        Queue::assertPushed(PublishOutcomeEventsJob::class, 1);
    }

    public function test_no_active_accounts_returns_failure(): void
    {
        Queue::fake();

        $this->artisan('ads:sync', ['action' => 'sync-campaigns'])
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    public function test_filter_by_specific_account(): void
    {
        Queue::fake();
        $this->createPlatformAccount('meta', 'act_111');
        $this->createPlatformAccount('meta', 'act_222');

        $this->artisan('ads:sync', ['action' => 'sync-campaigns', '--account' => 'act_111'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncCampaignStructureJob::class, 1);
        Queue::assertPushed(SyncCampaignStructureJob::class, function ($job) {
            return $job->accountId === 'act_111';
        });
    }
}
