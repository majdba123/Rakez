<?php

namespace Tests\Unit\Ads;

use App\Domain\Ads\ValueObjects\DateRange;
use App\Infrastructure\Ads\Snap\SnapClient;
use App\Infrastructure\Ads\Snap\SnapInsightsReader;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SnapInsightsReaderTest extends TestCase
{
    public function test_fetch_insights_maps_native_leads_and_uniques(): void
    {
        config(['ads_platforms.default_normalized_currency' => 'SAR']);

        $client = $this->createMock(SnapClient::class);
        $client->method('fetchStats')->willReturn([
            'timeseries_stats' => [
                [
                    'timeseries_stat' => [
                        'id' => 'camp_1',
                        'timeseries' => [
                            [
                                'start_time' => '2026-01-01T00:00:00Z',
                                'stats' => [
                                    'impressions' => 10,
                                    'swipes' => 2,
                                    'spend' => 2_500_000,
                                    'native_leads' => 3,
                                    'uniques' => 7,
                                    'conversion_purchases' => 1,
                                    'conversion_purchases_value' => 9_900_000,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $reader = new SnapInsightsReader($client);

        $rows = $reader->fetchInsights(
            'snap_111',
            'campaign',
            new DateRange(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-01')),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('camp_1', $rows[0]['entity_id']);
        $this->assertSame(2.5, $rows[0]['spend']);
        $this->assertSame('SAR', $rows[0]['spend_currency']);
        $this->assertSame(3, $rows[0]['leads']);
        $this->assertSame(7, $rows[0]['reach']);
        $this->assertSame(1, $rows[0]['conversions']);
        $this->assertSame(9.9, $rows[0]['revenue']);
    }
}

