<?php

namespace App\Infrastructure\Ads\Snap;

use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\ValueObjects\DateRange;
use App\Domain\Ads\ValueObjects\Platform;

final class SnapInsightsReader implements AdsReadPort
{
    public function __construct(
        private readonly SnapClient $client,
    ) {}

    public function platform(): Platform
    {
        return Platform::Snap;
    }

    public function listCampaigns(string $accountId): array
    {
        $campaigns = [];
        foreach ($this->client->paginate("adaccounts/{$accountId}/campaigns", [], $accountId) as $item) {
            $campaigns[] = [
                'id' => $item['id'] ?? '',
                'name' => $item['name'] ?? '',
                'status' => $item['status'] ?? $item['effective_status'] ?? '',
                'objective' => $item['objective'] ?? '',
            ];
        }

        return $campaigns;
    }

    public function listAdSets(string $accountId): array
    {
        $adSets = [];
        $campaigns = $this->listCampaigns($accountId);

        foreach ($campaigns as $campaign) {
            foreach ($this->client->paginate("campaigns/{$campaign['id']}/adsquads", [], $accountId) as $item) {
                $adSets[] = [
                    'id' => $item['id'] ?? '',
                    'name' => $item['name'] ?? '',
                    'status' => $item['status'] ?? $item['effective_status'] ?? '',
                    'campaign_id' => $campaign['id'],
                ];
            }
        }

        return $adSets;
    }

    public function listAds(string $accountId): array
    {
        $ads = [];
        $adSets = $this->listAdSets($accountId);

        foreach ($adSets as $adSet) {
            foreach ($this->client->paginate("adsquads/{$adSet['id']}/ads", [], $accountId) as $item) {
                $ads[] = [
                    'id' => $item['id'] ?? '',
                    'name' => $item['name'] ?? '',
                    'status' => $item['status'] ?? $item['effective_status'] ?? '',
                    'ad_set_id' => $adSet['id'],
                ];
            }
        }

        return $ads;
    }

    public function fetchInsights(
        string $accountId,
        string $level,
        DateRange $dateRange,
        array $fields = [],
    ): array {
        if (empty($fields)) {
            $fields = $this->defaultStatsFields();
        }

        $entityType = match ($level) {
            'ad' => 'ads',
            'adset', 'adsquad' => 'adsquads',
            default => 'campaigns',
        };

        $snapDates = $dateRange->toSnapIso();

        $params = [
            'granularity' => 'DAY',
            'fields' => implode(',', $fields),
            'start_time' => $snapDates['start_time'],
            'end_time' => $snapDates['end_time'],
        ];

        $statsResponse = $this->client->fetchStats(
            'adaccounts',
            $accountId,
            $params + ['breakdown' => $this->breakdownForLevel($level)],
            $accountId,
        );

        return $this->normalizeStatsResponse($statsResponse, $level);
    }

    private function defaultStatsFields(): array
    {
        return [
            'impressions', 'swipes', 'spend',
            'conversion_purchases', 'conversion_purchases_value',
            'video_views', 'video_views_25_percent',
        ];
    }

    private function breakdownForLevel(string $level): string
    {
        return match ($level) {
            'ad' => 'ad',
            'adset', 'adsquad' => 'adsquad',
            default => 'campaign',
        };
    }

    private function normalizeStatsResponse(array $response, string $level): array
    {
        $rows = [];

        $timeseries = $response['timeseries'] ?? $response['timeseries_stats'] ?? [];
        if (empty($timeseries)) {
            $timeseries = $response['total_stats'] ?? [];
            if (! empty($timeseries) && ! isset($timeseries[0])) {
                $timeseries = [$timeseries];
            }
        }

        foreach ($timeseries as $seriesItem) {
            $stats = $seriesItem['timeseries_stat'] ?? $seriesItem['total_stat'] ?? $seriesItem;
            $entityId = $stats['id'] ?? '';

            foreach ($stats['timeseries'] ?? [$stats] as $point) {
                $dateStart = isset($point['start_time'])
                    ? substr($point['start_time'], 0, 10)
                    : ($point['date'] ?? '');
                $dateStop = $dateStart;

                $s = $point['stats'] ?? $point;

                $rows[] = [
                    'entity_id' => $entityId,
                    'date_start' => $dateStart,
                    'date_stop' => $dateStop,
                    'impressions' => (int) ($s['impressions'] ?? 0),
                    'clicks' => (int) ($s['swipes'] ?? $s['clicks'] ?? 0),
                    'spend' => ((float) ($s['spend'] ?? 0)) / 1_000_000,
                    'spend_currency' => 'USD',
                    'conversions' => (int) ($s['conversion_purchases'] ?? 0),
                    'revenue' => ((float) ($s['conversion_purchases_value'] ?? 0)) / 1_000_000,
                    'video_views' => (int) ($s['video_views'] ?? 0),
                    'reach' => 0,
                    'raw_metrics' => $s,
                ];
            }
        }

        return $rows;
    }
}
