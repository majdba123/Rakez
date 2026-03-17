<?php

namespace Tests\Unit\Marketing\AI;

use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use App\Services\Marketing\AI\CampaignPerformanceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;

class CampaignPerformanceAggregatorTest extends TestCase
{
    use RefreshDatabase, TestsWithAds;

    private CampaignPerformanceAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new CampaignPerformanceAggregator;
    }

    public function test_by_platform_aggregates_all_platforms(): void
    {
        $this->seedInsightRows('meta', 2);
        $this->seedInsightRows('snap', 2);
        $this->seedInsightRows('tiktok', 2);

        $result = $this->aggregator->byPlatform();

        $this->assertCount(3, $result);
        $platforms = $result->pluck('platform')->sort()->values()->toArray();
        $this->assertSame(['meta', 'snap', 'tiktok'], $platforms);
    }

    public function test_by_platform_with_date_filters(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-10',
            'date_stop' => '2026-01-10',
            'breakdown_hash' => 'none',
            'impressions' => 5000,
            'clicks' => 100,
            'spend' => 50.0,
            'conversions' => 10,
            'revenue' => 500.0,
            'reach' => 4000,
        ]);
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c2',
            'date_start' => '2026-01-20',
            'date_stop' => '2026-01-20',
            'breakdown_hash' => 'none',
            'impressions' => 3000,
            'clicks' => 60,
            'spend' => 30.0,
            'conversions' => 6,
            'revenue' => 300.0,
            'reach' => 2500,
        ]);

        $result = $this->aggregator->byPlatform('2026-01-15', '2026-01-25');

        $this->assertCount(1, $result);
        $meta = $result->first();
        $this->assertSame('meta', $meta->platform);
        $this->assertSame(30.0, $meta->totalSpend);
        $this->assertSame(60, $meta->totalClicks);
        $this->assertSame(6, $meta->totalConversions);
        $this->assertSame(5.0, $meta->cpl);
        $this->assertSame(10.0, $meta->roas);
    }

    public function test_by_platform_zero_clicks_does_not_divide_by_zero(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-01',
            'breakdown_hash' => 'none',
            'impressions' => 1000,
            'clicks' => 0,
            'spend' => 20.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 800,
        ]);

        $result = $this->aggregator->byPlatform('2026-01-01', '2026-01-01');

        $this->assertCount(1, $result);
        $meta = $result->first();
        $this->assertSame(0.0, $meta->cpc);
        $this->assertSame(0.0, $meta->cpl);
        $this->assertSame(0.0, $meta->ctr);
        $this->assertSame(0.0, $meta->conversionRate);
        $this->assertSame(0.0, $meta->roas);
    }

    public function test_by_campaign_returns_per_campaign_metrics(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'camp_1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-01',
            'breakdown_hash' => 'none',
            'impressions' => 10000,
            'clicks' => 200,
            'spend' => 100.0,
            'conversions' => 20,
            'revenue' => 2000.0,
            'reach' => 8000,
        ]);

        $result = $this->aggregator->byCampaign('meta', '2026-01-01', '2026-01-01');

        $this->assertCount(1, $result);
        $row = $result->first();
        $this->assertSame('camp_1', $row['campaign_id']);
        $this->assertSame(100.0, $row['total_spend']);
        $this->assertSame(0.5, $row['cpc']);
        $this->assertSame(5.0, $row['cpl']);
        $this->assertSame(20.0, $row['roas']);
    }

    public function test_daily_trend_groups_by_date(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-01',
            'breakdown_hash' => 'none',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 25.0,
            'conversions' => 5,
            'revenue' => 250.0,
            'reach' => 900,
        ]);
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c2',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-01',
            'breakdown_hash' => 'none',
            'impressions' => 500,
            'clicks' => 25,
            'spend' => 15.0,
            'conversions' => 3,
            'revenue' => 150.0,
            'reach' => 400,
        ]);

        $result = $this->aggregator->dailyTrend('meta', '2026-01-01', '2026-01-01');

        $this->assertCount(1, $result);
        $day = $result->first();
        $this->assertSame('2026-01-01', $day['date']);
        $this->assertSame(40.0, $day['spend']);
        $this->assertSame(75, $day['clicks']);
        $this->assertSame(8, $day['conversions']);
        $this->assertSame(5.0, $day['cpl']);
    }

    public function test_period_comparison_returns_changes(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-07',
            'breakdown_hash' => 'none',
            'impressions' => 1000,
            'clicks' => 100,
            'spend' => 50.0,
            'conversions' => 10,
            'revenue' => 500.0,
            'reach' => 800,
        ]);
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-08',
            'date_stop' => '2026-01-14',
            'breakdown_hash' => 'none',
            'impressions' => 2000,
            'clicks' => 200,
            'spend' => 100.0,
            'conversions' => 20,
            'revenue' => 1000.0,
            'reach' => 1600,
        ]);

        $result = $this->aggregator->periodComparison(
            '2026-01-08',
            '2026-01-14',
            '2026-01-01',
            '2026-01-07'
        );

        $this->assertArrayHasKey('meta', $result);
        $meta = $result['meta'];
        $this->assertNotNull($meta['current']);
        $this->assertNotNull($meta['previous']);
        $this->assertNotNull($meta['changes']);
        $this->assertSame(100.0, $meta['current']->totalSpend);
        $this->assertSame(50.0, $meta['previous']->totalSpend);
        $this->assertSame(100.0, $meta['changes']['spend_change_pct']);
    }

    public function test_benchmark_against_guardrails(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-01',
            'breakdown_hash' => 'none',
            'impressions' => 10000,
            'clicks' => 200,
            'spend' => 100.0,
            'conversions' => 5,
            'revenue' => 500.0,
            'reach' => 8000,
        ]);

        $result = $this->aggregator->benchmarkAgainstGuardrails('2026-01-01', '2026-01-01');

        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('actual_cpl', $result['meta']);
        $this->assertArrayHasKey('cpl_status', $result['meta']);
        $this->assertArrayHasKey('actual_roas', $result['meta']);
    }

    public function test_has_enough_data_returns_false_when_less_than_minimum_days(): void
    {
        $this->seedInsightRows('meta', 5);

        $this->assertFalse($this->aggregator->hasEnoughData(30));
    }

    public function test_has_enough_data_returns_true_when_at_least_minimum_days(): void
    {
        for ($i = 0; $i < 35; $i++) {
            AdsInsightRow::create([
                'platform' => 'meta',
                'account_id' => 'a1',
                'level' => 'campaign',
                'entity_id' => 'c1',
                'date_start' => now()->subDays($i)->toDateString(),
                'date_stop' => now()->subDays($i)->toDateString(),
                'breakdown_hash' => 'none',
                'impressions' => 100,
                'clicks' => 10,
                'spend' => 5.0,
                'conversions' => 1,
                'revenue' => 50.0,
                'reach' => 80,
            ]);
        }

        $this->assertTrue($this->aggregator->hasEnoughData(30));
    }

    public function test_data_available_days_returns_count(): void
    {
        $this->seedInsightRows('meta', 3);

        $count = $this->aggregator->dataAvailableDays();

        $this->assertSame(3, $count);
    }
}
