<?php

namespace App\Services\Marketing\AI;

use App\Domain\Marketing\ValueObjects\PlatformPerformanceSummary;
use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignPerformanceAggregator
{
    /**
     * Get aggregated performance per platform for a date range.
     *
     * @return Collection<int, PlatformPerformanceSummary>
     */
    public function byPlatform(?string $dateStart = null, ?string $dateEnd = null): Collection
    {
        $query = AdsInsightRow::select(
            'platform',
            DB::raw('SUM(spend) as total_spend'),
            DB::raw('SUM(impressions) as total_impressions'),
            DB::raw('SUM(clicks) as total_clicks'),
            DB::raw('SUM(conversions) as total_conversions'),
            DB::raw('SUM(revenue) as total_revenue'),
            DB::raw('SUM(reach) as total_reach'),
        )->groupBy('platform');

        $this->applyDateFilters($query, $dateStart, $dateEnd);

        return $query->get()->map(fn ($row) => $this->toSummary($row, $dateStart, $dateEnd));
    }

    /**
     * Get performance for a specific platform broken down by campaign.
     */
    public function byCampaign(string $platform, ?string $dateStart = null, ?string $dateEnd = null): Collection
    {
        $query = AdsInsightRow::select(
            'entity_id',
            DB::raw('SUM(spend) as total_spend'),
            DB::raw('SUM(impressions) as total_impressions'),
            DB::raw('SUM(clicks) as total_clicks'),
            DB::raw('SUM(conversions) as total_conversions'),
            DB::raw('SUM(revenue) as total_revenue'),
            DB::raw('SUM(reach) as total_reach'),
        )
            ->where('platform', $platform)
            ->where('level', 'campaign')
            ->groupBy('entity_id');

        $this->applyDateFilters($query, $dateStart, $dateEnd);

        return $query->get()->map(fn ($row) => [
            'campaign_id' => $row->entity_id,
            'total_spend' => round((float) $row->total_spend, 2),
            'impressions' => (int) $row->total_impressions,
            'clicks' => (int) $row->total_clicks,
            'conversions' => (int) $row->total_conversions,
            'revenue' => round((float) $row->total_revenue, 2),
            'cpc' => $row->total_clicks > 0 ? round($row->total_spend / $row->total_clicks, 2) : 0,
            'cpl' => $row->total_conversions > 0 ? round($row->total_spend / $row->total_conversions, 2) : 0,
            'roas' => $row->total_spend > 0 ? round($row->total_revenue / $row->total_spend, 2) : 0,
        ]);
    }

    /**
     * Daily time-series for a platform (for trend analysis).
     */
    public function dailyTrend(string $platform, ?string $dateStart = null, ?string $dateEnd = null): Collection
    {
        $query = AdsInsightRow::select(
            'date_start',
            DB::raw('SUM(spend) as spend'),
            DB::raw('SUM(impressions) as impressions'),
            DB::raw('SUM(clicks) as clicks'),
            DB::raw('SUM(conversions) as conversions'),
            DB::raw('SUM(revenue) as revenue'),
        )
            ->where('platform', $platform)
            ->groupBy('date_start')
            ->orderBy('date_start');

        $this->applyDateFilters($query, $dateStart, $dateEnd);

        return $query->get()->map(fn ($row) => [
            'date' => $row->date_start,
            'spend' => round((float) $row->spend, 2),
            'impressions' => (int) $row->impressions,
            'clicks' => (int) $row->clicks,
            'conversions' => (int) $row->conversions,
            'revenue' => round((float) $row->revenue, 2),
            'cpl' => $row->conversions > 0 ? round($row->spend / $row->conversions, 2) : 0,
        ]);
    }

    /**
     * Compare current period performance vs a previous period.
     */
    public function periodComparison(string $currentStart, string $currentEnd, string $previousStart, string $previousEnd): array
    {
        $current = $this->byPlatform($currentStart, $currentEnd)->keyBy('platform');
        $previous = $this->byPlatform($previousStart, $previousEnd)->keyBy('platform');

        $comparison = [];
        foreach (['meta', 'snap', 'tiktok'] as $platform) {
            $cur = $current->get($platform);
            $prev = $previous->get($platform);

            $comparison[$platform] = [
                'current' => $cur?->toArray(),
                'previous' => $prev?->toArray(),
                'changes' => $this->calculateChanges($cur, $prev),
            ];
        }

        return $comparison;
    }

    /**
     * Benchmark actual data vs static guardrail ranges.
     */
    public function benchmarkAgainstGuardrails(?string $dateStart = null, ?string $dateEnd = null): array
    {
        $platformData = $this->byPlatform($dateStart, $dateEnd);
        $guardrails = config('ai_guardrails', []);
        $results = [];

        $platformChannelMap = [
            'meta' => 'instagram',
            'snap' => 'snapchat',
            'tiktok' => 'tiktok',
        ];

        foreach ($platformData as $summary) {
            $channelKey = $platformChannelMap[$summary->platform] ?? null;
            $channelCpl = $guardrails['cpl']['channels'][$channelKey] ?? null;

            $cplStatus = 'unknown';
            if ($channelCpl && $summary->cpl > 0) {
                if ($summary->cpl < $channelCpl['min']) {
                    $cplStatus = 'below_benchmark';
                } elseif ($summary->cpl > $channelCpl['max']) {
                    $cplStatus = 'above_benchmark';
                } else {
                    $cplStatus = 'within_range';
                }
            }

            $results[$summary->platform] = [
                'actual_cpl' => $summary->cpl,
                'benchmark_range' => $channelCpl,
                'cpl_status' => $cplStatus,
                'actual_roas' => $summary->roas,
                'is_outperformer' => $summary->roas > 3.0,
            ];
        }

        return $results;
    }

    /**
     * Check if enough data exists for reliable analysis.
     */
    public function hasEnoughData(int $minimumDays = 30): bool
    {
        $distinctDays = AdsInsightRow::select('date_start')
            ->distinct()
            ->count('date_start');

        return $distinctDays >= $minimumDays;
    }

    public function dataAvailableDays(): int
    {
        return AdsInsightRow::select('date_start')
            ->distinct()
            ->count('date_start');
    }

    private function toSummary(object $row, ?string $dateStart, ?string $dateEnd): PlatformPerformanceSummary
    {
        $spend = (float) $row->total_spend;
        $impressions = (int) $row->total_impressions;
        $clicks = (int) $row->total_clicks;
        $conversions = (int) $row->total_conversions;
        $revenue = (float) $row->total_revenue;
        $reach = (int) $row->total_reach;

        return new PlatformPerformanceSummary(
            platform: $row->platform,
            totalSpend: $spend,
            totalImpressions: $impressions,
            totalClicks: $clicks,
            totalConversions: $conversions,
            totalRevenue: $revenue,
            totalReach: $reach,
            cpc: $clicks > 0 ? $spend / $clicks : 0,
            cpl: $conversions > 0 ? $spend / $conversions : 0,
            ctr: $impressions > 0 ? $clicks / $impressions : 0,
            conversionRate: $clicks > 0 ? $conversions / $clicks : 0,
            roas: $spend > 0 ? $revenue / $spend : 0,
            dateStart: $dateStart,
            dateEnd: $dateEnd,
        );
    }

    private function applyDateFilters($query, ?string $dateStart, ?string $dateEnd): void
    {
        if ($dateStart) {
            $query->where('date_start', '>=', $dateStart);
        }
        if ($dateEnd) {
            $query->where('date_stop', '<=', $dateEnd);
        }
    }

    private function calculateChanges(?PlatformPerformanceSummary $current, ?PlatformPerformanceSummary $previous): ?array
    {
        if (! $current || ! $previous) {
            return null;
        }

        $pctChange = fn (float $cur, float $prev) => $prev > 0 ? round((($cur - $prev) / $prev) * 100, 1) : null;

        return [
            'spend_change_pct' => $pctChange($current->totalSpend, $previous->totalSpend),
            'cpl_change_pct' => $pctChange($current->cpl, $previous->cpl),
            'roas_change_pct' => $pctChange($current->roas, $previous->roas),
            'conversions_change_pct' => $pctChange($current->totalConversions, $previous->totalConversions),
        ];
    }
}
