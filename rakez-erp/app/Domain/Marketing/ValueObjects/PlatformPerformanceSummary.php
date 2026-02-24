<?php

namespace App\Domain\Marketing\ValueObjects;

final readonly class PlatformPerformanceSummary
{
    public function __construct(
        public string $platform,
        public float $totalSpend,
        public int $totalImpressions,
        public int $totalClicks,
        public int $totalConversions,
        public float $totalRevenue,
        public int $totalReach,
        public float $cpc,
        public float $cpl,
        public float $ctr,
        public float $conversionRate,
        public float $roas,
        public string $currency = 'SAR',
        public ?string $dateStart = null,
        public ?string $dateEnd = null,
    ) {}

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'total_spend' => round($this->totalSpend, 2),
            'total_impressions' => $this->totalImpressions,
            'total_clicks' => $this->totalClicks,
            'total_conversions' => $this->totalConversions,
            'total_revenue' => round($this->totalRevenue, 2),
            'total_reach' => $this->totalReach,
            'cpc' => round($this->cpc, 2),
            'cpl' => round($this->cpl, 2),
            'ctr' => round($this->ctr, 4),
            'conversion_rate' => round($this->conversionRate, 4),
            'roas' => round($this->roas, 2),
            'currency' => $this->currency,
            'date_start' => $this->dateStart,
            'date_end' => $this->dateEnd,
        ];
    }
}
