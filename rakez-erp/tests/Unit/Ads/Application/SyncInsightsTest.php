<?php

namespace Tests\Unit\Ads\Application;

use App\Application\Ads\SyncInsights;
use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\Ports\InsightStorePort;
use App\Domain\Ads\ValueObjects\DateRange;
use App\Domain\Ads\ValueObjects\Platform;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SyncInsightsTest extends TestCase
{
    public function test_execute_calls_fetch_insights_and_upsert_for_each_level(): void
    {
        $reader = $this->createMock(AdsReadPort::class);
        $reader->method('platform')->willReturn(Platform::Meta);
        $reader->method('fetchInsights')
            ->willReturnCallback(function (string $accountId, string $level) {
                return [
                    [
                        'entity_id' => "{$level}_1",
                        'date_start' => '2026-01-01',
                        'date_stop' => '2026-01-01',
                        'impressions' => 1000,
                        'clicks' => 50,
                        'spend' => 25.5,
                    ],
                ];
            });

        $store = $this->createMock(InsightStorePort::class);
        $store->expects($this->exactly(3))
            ->method('upsertInsights')
            ->with(
                $this->identicalTo(Platform::Meta),
                $this->equalTo('act_123'),
                $this->logicalOr('campaign', 'adset', 'ad'),
                $this->isType('array')
            );

        $dateRange = new DateRange(
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-07'),
        );

        $useCase = new SyncInsights($reader, $store);
        $useCase->execute('act_123', $dateRange, ['campaign', 'adset', 'ad']);

        $this->assertTrue(true);
    }

    public function test_execute_calls_fetch_insights_once_when_single_level(): void
    {
        $reader = $this->createMock(AdsReadPort::class);
        $reader->method('platform')->willReturn(Platform::Snap);
        $reader->expects($this->once())
            ->method('fetchInsights')
            ->with('snap_acc', 'campaign', $this->isInstanceOf(DateRange::class), [])
            ->willReturn([]);

        $store = $this->createMock(InsightStorePort::class);
        $store->expects($this->once())
            ->method('upsertInsights')
            ->with(Platform::Snap, 'snap_acc', 'campaign', []);

        $dateRange = new DateRange(
            CarbonImmutable::parse('2026-02-01'),
            CarbonImmutable::parse('2026-02-28'),
        );

        $useCase = new SyncInsights($reader, $store);
        $useCase->execute('snap_acc', $dateRange, ['campaign']);
    }
}
