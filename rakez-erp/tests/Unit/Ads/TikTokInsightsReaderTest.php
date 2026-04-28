<?php

namespace Tests\Unit\Ads;

use App\Domain\Ads\ValueObjects\DateRange;
use App\Infrastructure\Ads\TikTok\TikTokClient;
use App\Infrastructure\Ads\TikTok\TikTokInsightsReader;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class TikTokInsightsReaderTest extends TestCase
{
    public function test_fetch_insights_sends_required_service_type_and_normalizes_row(): void
    {
        config(['ads_platforms.default_normalized_currency' => 'AED']);

        /** @var TikTokClient&MockObject $client */
        $client = $this->createMock(TikTokClient::class);
        $client->method('get')->willReturnCallback(function (string $endpoint, array $params) {
            $this->assertSame('report/integrated/get/', $endpoint);
            $this->assertSame('AUCTION', $params['service_type'] ?? null);
            $this->assertSame('CAMPAIGN', $params['data_level'] ?? null);

            $dimensions = json_decode((string) ($params['dimensions'] ?? '[]'), true);
            $this->assertSame(['campaign_id', 'stat_time_day'], $dimensions);

            return [
                'data' => [
                    'list' => [
                        [
                            'dimensions' => [
                                'campaign_id' => 'c1',
                                'stat_time_day' => '2026-01-01',
                            ],
                            'metrics' => [
                                'spend' => '12.34',
                                'impressions' => '100',
                                'clicks' => '5',
                                'reach' => '50',
                                'conversion' => '2',
                                'video_play_actions' => '10',
                            ],
                        ],
                    ],
                    'page_info' => [
                        'total_number' => 1,
                    ],
                ],
            ];
        });

        $reader = new TikTokInsightsReader($client);
        $rows = $reader->fetchInsights(
            'tt_111',
            'campaign',
            new DateRange(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-01')),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('c1', $rows[0]['entity_id']);
        $this->assertSame('AED', $rows[0]['spend_currency']);
        $this->assertSame(12.34, $rows[0]['spend']);
        $this->assertSame(2, $rows[0]['conversions']);
        $this->assertSame(0.0, $rows[0]['revenue']);
    }
}

