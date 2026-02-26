<?php

namespace Tests\Unit\Ads\ValueObjects;

use App\Domain\Ads\ValueObjects\DateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function test_construction_with_start_and_end(): void
    {
        $start = CarbonImmutable::parse('2026-01-01');
        $end = CarbonImmutable::parse('2026-01-31');
        $range = new DateRange($start, $end);

        $this->assertTrue($start->equalTo($range->start));
        $this->assertTrue($end->equalTo($range->end));
    }

    public function test_last_days_factory(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-24'));

        $range = DateRange::lastDays(7);

        $this->assertSame('2026-02-17', $range->start->toDateString());
        $this->assertSame('2026-02-24', $range->end->toDateString());

        CarbonImmutable::setTestNow();
    }

    public function test_to_meta_time_range(): void
    {
        $range = new DateRange(
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-07'),
        );

        $meta = $range->toMetaTimeRange();

        $this->assertSame('2026-01-01', $meta['since']);
        $this->assertSame('2026-01-07', $meta['until']);
    }

    public function test_to_snap_iso(): void
    {
        $range = new DateRange(
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-07'),
        );

        $snap = $range->toSnapIso();

        $this->assertArrayHasKey('start_time', $snap);
        $this->assertArrayHasKey('end_time', $snap);
        $this->assertStringContainsString('2026-01-01', $snap['start_time']);
        $this->assertStringContainsString('2026-01-07', $snap['end_time']);
        $this->assertStringContainsString('T', $snap['start_time']);
    }

    public function test_to_tiktok_dates(): void
    {
        $range = new DateRange(
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-07'),
        );

        $tt = $range->toTikTokDates();

        $this->assertSame('2026-01-01', $tt['start_date']);
        $this->assertSame('2026-01-07', $tt['end_date']);
    }
}
