<?php

namespace App\Domain\Ads\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function lastDays(int $days): self
    {
        $end = CarbonImmutable::today();
        $start = $end->subDays($days);

        return new self($start, $end);
    }

    public function toMetaTimeRange(): array
    {
        return [
            'since' => $this->start->toDateString(),
            'until' => $this->end->toDateString(),
        ];
    }

    public function toSnapIso(): array
    {
        return [
            'start_time' => $this->start->startOfDay()->toIso8601String(),
            'end_time' => $this->end->endOfDay()->toIso8601String(),
        ];
    }

    public function toTikTokDates(): array
    {
        return [
            'start_date' => $this->start->toDateString(),
            'end_date' => $this->end->toDateString(),
        ];
    }
}
