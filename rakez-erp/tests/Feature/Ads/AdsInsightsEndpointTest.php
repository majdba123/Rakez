<?php

namespace Tests\Feature\Ads;

use App\Jobs\Ads\SyncCampaignStructureJob;
use App\Jobs\Ads\SyncInsightsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsInsightsEndpointTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    public function test_accounts_endpoint_returns_active_accounts(): void
    {
        $user = $this->createMarketingUser();
        $this->createPlatformAccount('meta', 'act_111');
        $this->createPlatformAccount('snap', 'snap_222');
        $this->createPlatformAccount('tiktok', 'tt_333', ['is_active' => false]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/accounts');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_campaigns_endpoint_returns_campaigns(): void
    {
        $user = $this->createMarketingUser();
        \App\Infrastructure\Ads\Persistence\Models\AdsCampaign::create([
            'platform' => 'meta',
            'account_id' => 'act_111',
            'campaign_id' => 'camp_1',
            'name' => 'Test Campaign',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/campaigns');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Test Campaign');
    }

    public function test_campaigns_endpoint_filters_by_platform(): void
    {
        $user = $this->createMarketingUser();
        \App\Infrastructure\Ads\Persistence\Models\AdsCampaign::create([
            'platform' => 'meta', 'account_id' => 'a1', 'campaign_id' => 'c1', 'name' => 'Meta Camp',
        ]);
        \App\Infrastructure\Ads\Persistence\Models\AdsCampaign::create([
            'platform' => 'snap', 'account_id' => 'a2', 'campaign_id' => 'c2', 'name' => 'Snap Camp',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/campaigns?platform=snap');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('snap', $data[0]['platform']);
    }

    public function test_insights_endpoint_returns_paginated_insights(): void
    {
        $user = $this->createMarketingUser();
        $this->seedInsightRows('meta', 5);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/insights?platform=meta');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page']);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_insights_endpoint_filters_by_date_range(): void
    {
        $user = $this->createMarketingUser();
        $this->seedInsightRows('meta', 10);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/insights?date_start=' . now()->subDays(3)->toDateString());

        $response->assertOk();
    }

    public function test_trigger_sync_dispatches_campaign_job(): void
    {
        Queue::fake();
        $user = $this->createMarketingUser();
        $this->createPlatformAccount('meta', 'act_123');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/sync', [
                'platform' => 'meta',
                'account_id' => 'act_123',
                'action' => 'campaigns',
            ]);

        $response->assertOk();
        Queue::assertPushed(SyncCampaignStructureJob::class);
    }

    public function test_trigger_sync_dispatches_insights_job(): void
    {
        Queue::fake();
        $user = $this->createMarketingUser();
        $this->createPlatformAccount('snap', 'snap_acc');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/sync', [
                'platform' => 'snap',
                'account_id' => 'snap_acc',
                'action' => 'insights',
                'days' => 14,
            ]);

        $response->assertOk();
        Queue::assertPushed(SyncInsightsJob::class);
    }

    public function test_sync_endpoint_validates_platform(): void
    {
        $user = $this->createMarketingUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/sync', [
                'platform' => 'google',
                'account_id' => 'acc_1',
                'action' => 'campaigns',
            ]);

        $response->assertStatus(422);
    }

    public function test_endpoints_reject_unauthenticated(): void
    {
        $this->getJson('/api/ads/accounts')->assertStatus(401);
        $this->getJson('/api/ads/campaigns')->assertStatus(401);
        $this->getJson('/api/ads/insights')->assertStatus(401);
        $this->postJson('/api/ads/sync')->assertStatus(401);
    }

    public function test_insights_endpoint_filters_by_level_campaign(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);
        $this->seedInsightRows('meta', 3);
        \App\Infrastructure\Ads\Persistence\Models\AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'act_123456',
            'level' => 'adset',
            'entity_id' => 'adset_1',
            'date_start' => now()->toDateString(),
            'date_stop' => now()->toDateString(),
            'breakdown_hash' => 'none',
            'impressions' => 100,
            'clicks' => 10,
            'spend' => 5.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 80,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/insights?platform=meta&level=campaign');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $row) {
            $this->assertSame('campaign', $row['level']);
        }
    }

    public function test_insights_endpoint_filters_by_level_adset(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);
        \App\Infrastructure\Ads\Persistence\Models\AdsInsightRow::create([
            'platform' => 'snap',
            'account_id' => 'snap_1',
            'level' => 'adset',
            'entity_id' => 'adset_1',
            'date_start' => now()->toDateString(),
            'date_stop' => now()->toDateString(),
            'breakdown_hash' => 'none',
            'impressions' => 200,
            'clicks' => 20,
            'spend' => 10.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 150,
        ]);
        \App\Infrastructure\Ads\Persistence\Models\AdsInsightRow::create([
            'platform' => 'snap',
            'account_id' => 'snap_1',
            'level' => 'campaign',
            'entity_id' => 'camp_1',
            'date_start' => now()->toDateString(),
            'date_stop' => now()->toDateString(),
            'breakdown_hash' => 'none',
            'impressions' => 500,
            'clicks' => 50,
            'spend' => 25.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 400,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/insights?platform=snap&level=adset');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('adset', $data[0]['level']);
    }
}
