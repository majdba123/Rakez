<?php

namespace Tests\Integration\Ads\Persistence;

use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\EloquentInsightStore;
use App\Infrastructure\Ads\Persistence\Models\AdsAd;
use App\Infrastructure\Ads\Persistence\Models\AdsAdSet;
use App\Infrastructure\Ads\Persistence\Models\AdsCampaign;
use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentInsightStoreTest extends TestCase
{
    use RefreshDatabase;

    private EloquentInsightStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new EloquentInsightStore();
    }

    public function test_upsert_campaigns_creates_rows(): void
    {
        $this->store->upsertCampaigns(Platform::Meta, 'act_123', [
            ['id' => 'camp_1', 'name' => 'Campaign 1', 'status' => 'ACTIVE', 'objective' => 'CONVERSIONS'],
            ['id' => 'camp_2', 'name' => 'Campaign 2', 'status' => 'PAUSED', 'objective' => 'REACH'],
        ]);

        $this->assertDatabaseCount('ads_campaigns', 2);
        $this->assertDatabaseHas('ads_campaigns', [
            'platform' => 'meta',
            'campaign_id' => 'camp_1',
            'name' => 'Campaign 1',
        ]);
    }

    public function test_upsert_campaigns_updates_existing(): void
    {
        $this->store->upsertCampaigns(Platform::Meta, 'act_123', [
            ['id' => 'camp_1', 'name' => 'Original', 'status' => 'ACTIVE'],
        ]);

        $this->store->upsertCampaigns(Platform::Meta, 'act_123', [
            ['id' => 'camp_1', 'name' => 'Updated', 'status' => 'PAUSED'],
        ]);

        $this->assertDatabaseCount('ads_campaigns', 1);
        $this->assertDatabaseHas('ads_campaigns', [
            'campaign_id' => 'camp_1',
            'name' => 'Updated',
            'status' => 'PAUSED',
        ]);
    }

    public function test_upsert_ad_sets_creates_and_upserts(): void
    {
        $this->store->upsertAdSets(Platform::Snap, 'acc_1', [
            ['id' => 'as_1', 'name' => 'AdSquad 1', 'status' => 'ACTIVE', 'campaign_id' => 'c_1'],
        ]);

        $this->assertDatabaseCount('ads_ad_sets', 1);

        $this->store->upsertAdSets(Platform::Snap, 'acc_1', [
            ['id' => 'as_1', 'name' => 'AdSquad Updated', 'status' => 'PAUSED', 'campaign_id' => 'c_1'],
        ]);

        $this->assertDatabaseCount('ads_ad_sets', 1);
        $this->assertDatabaseHas('ads_ad_sets', ['name' => 'AdSquad Updated']);
    }

    public function test_upsert_ads_creates_and_upserts(): void
    {
        $this->store->upsertAds(Platform::TikTok, 'adv_1', [
            ['id' => 'ad_1', 'name' => 'Ad 1', 'status' => 'ENABLE', 'ad_set_id' => 'ag_1'],
        ]);

        $this->assertDatabaseCount('ads_ads', 1);

        $this->store->upsertAds(Platform::TikTok, 'adv_1', [
            ['id' => 'ad_1', 'name' => 'Ad Updated', 'status' => 'DISABLE', 'ad_set_id' => 'ag_1'],
        ]);

        $this->assertDatabaseCount('ads_ads', 1);
        $this->assertDatabaseHas('ads_ads', ['name' => 'Ad Updated']);
    }

    public function test_upsert_insights_creates_rows(): void
    {
        $this->store->upsertInsights(Platform::Meta, 'act_123', 'campaign', [
            [
                'entity_id' => 'camp_1',
                'date_start' => '2026-01-01',
                'date_stop' => '2026-01-01',
                'impressions' => 10000,
                'clicks' => 500,
                'spend' => 150.50,
                'conversions' => 25,
                'revenue' => 2500.00,
            ],
        ]);

        $this->assertDatabaseCount('ads_insight_rows', 1);
        $this->assertDatabaseHas('ads_insight_rows', [
            'entity_id' => 'camp_1',
            'impressions' => 10000,
            'clicks' => 500,
        ]);
    }

    public function test_upsert_insights_idempotent_by_composite_key(): void
    {
        $row = [
            'entity_id' => 'camp_1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-01',
            'impressions' => 10000,
            'clicks' => 500,
            'spend' => 150.50,
        ];

        $this->store->upsertInsights(Platform::Meta, 'act_123', 'campaign', [$row]);
        $this->store->upsertInsights(Platform::Meta, 'act_123', 'campaign', [
            array_merge($row, ['impressions' => 12000]),
        ]);

        $this->assertDatabaseCount('ads_insight_rows', 1);
        $this->assertDatabaseHas('ads_insight_rows', ['impressions' => 12000]);
    }

    public function test_different_dates_create_separate_rows(): void
    {
        $base = ['entity_id' => 'camp_1', 'impressions' => 1000, 'clicks' => 50, 'spend' => 10];

        $this->store->upsertInsights(Platform::Meta, 'act_123', 'campaign', [
            array_merge($base, ['date_start' => '2026-01-01', 'date_stop' => '2026-01-01']),
            array_merge($base, ['date_start' => '2026-01-02', 'date_stop' => '2026-01-02']),
        ]);

        $this->assertDatabaseCount('ads_insight_rows', 2);
    }
}
