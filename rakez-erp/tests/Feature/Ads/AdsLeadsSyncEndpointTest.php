<?php

namespace Tests\Feature\Ads;

use App\Jobs\Ads\SyncLeadsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsLeadsSyncEndpointTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    public function test_leads_sync_endpoint_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.manage']);

        $this->createPlatformAccount('meta', 'act_111');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/leads/sync', [
                'platform' => 'meta',
                'account_id' => 'act_111',
                'campaign_id' => 'camp_1',
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ]);

        $response->assertOk();
        Queue::assertPushed(SyncLeadsJob::class, function (SyncLeadsJob $job) {
            return $job->platform === 'meta' && $job->accountId === 'act_111';
        });
    }
}

