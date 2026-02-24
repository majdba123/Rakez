<?php

namespace App\Infrastructure\Ads\TikTok;

use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\ValueObjects\DateRange;
use App\Domain\Ads\ValueObjects\Platform;

final class TikTokInsightsReader implements AdsReadPort
{
    public function __construct(
        private readonly TikTokClient $client,
    ) {}

    public function platform(): Platform
    {
        return Platform::TikTok;
    }

    public function listCampaigns(string $accountId): array
    {
        $advertiserId = $this->resolveAdvertiserId($accountId);
        $campaigns = [];

        foreach ($this->client->paginate('campaign/get/', [
            'advertiser_id' => $advertiserId,
        ], $accountId) as $item) {
            $campaigns[] = [
                'id' => $item['campaign_id'] ?? '',
                'name' => $item['campaign_name'] ?? '',
                'status' => $item['operation_status'] ?? $item['secondary_status'] ?? '',
                'objective' => $item['objective_type'] ?? '',
            ];
        }

        return $campaigns;
    }

    public function listAdSets(string $accountId): array
    {
        $advertiserId = $this->resolveAdvertiserId($accountId);
        $adGroups = [];

        foreach ($this->client->paginate('adgroup/get/', [
            'advertiser_id' => $advertiserId,
        ], $accountId) as $item) {
            $adGroups[] = [
                'id' => $item['adgroup_id'] ?? '',
                'name' => $item['adgroup_name'] ?? '',
                'status' => $item['operation_status'] ?? $item['secondary_status'] ?? '',
                'campaign_id' => $item['campaign_id'] ?? '',
            ];
        }

        return $adGroups;
    }

    public function listAds(string $accountId): array
    {
        $advertiserId = $this->resolveAdvertiserId($accountId);
        $ads = [];

        foreach ($this->client->paginate('ad/get/', [
            'advertiser_id' => $advertiserId,
        ], $accountId) as $item) {
            $ads[] = [
                'id' => $item['ad_id'] ?? '',
                'name' => $item['ad_name'] ?? '',
                'status' => $item['operation_status'] ?? $item['secondary_status'] ?? '',
                'ad_set_id' => $item['adgroup_id'] ?? '',
            ];
        }

        return $ads;
    }

    public function fetchInsights(
        string $accountId,
        string $level,
        DateRange $dateRange,
        array $fields = [],
    ): array {
        $advertiserId = $this->resolveAdvertiserId($accountId);
        $tikTokDates = $dateRange->toTikTokDates();

        $dataLevel = match ($level) {
            'ad' => 'AUCTION_AD',
            'adset', 'adgroup' => 'AUCTION_ADGROUP',
            default => 'AUCTION_CAMPAIGN',
        };

        $dimensions = match ($level) {
            'ad' => ['ad_id', 'stat_time_day'],
            'adset', 'adgroup' => ['adgroup_id', 'stat_time_day'],
            default => ['campaign_id', 'stat_time_day'],
        };

        if (empty($fields)) {
            $fields = $this->defaultMetrics();
        }

        $params = [
            'advertiser_id' => $advertiserId,
            'report_type' => 'BASIC',
            'data_level' => $dataLevel,
            'dimensions' => json_encode($dimensions),
            'metrics' => json_encode($fields),
            'start_date' => $tikTokDates['start_date'],
            'end_date' => $tikTokDates['end_date'],
            'page_size' => 200,
        ];

        $rows = [];
        $page = 1;

        do {
            $params['page'] = $page;
            $response = $this->client->get('report/integrated/get/', $params, $accountId);

            $data = $response['data'] ?? [];
            $list = $data['list'] ?? [];

            foreach ($list as $item) {
                $rows[] = $this->normalizeReportRow($item, $level);
            }

            $pageInfo = $data['page_info'] ?? [];
            $totalPages = (int) ceil(($pageInfo['total_number'] ?? 0) / 200);
            $page++;
        } while ($page <= $totalPages && ! empty($list));

        return $rows;
    }

    private function defaultMetrics(): array
    {
        return [
            'spend', 'impressions', 'clicks', 'reach',
            'conversion', 'cost_per_conversion', 'conversion_rate',
            'total_complete_payment_rate', 'complete_payment',
            'video_play_actions', 'video_watched_6s',
        ];
    }

    private function normalizeReportRow(array $item, string $level): array
    {
        $dims = $item['dimensions'] ?? [];
        $metrics = $item['metrics'] ?? [];

        $entityId = match ($level) {
            'ad' => $dims['ad_id'] ?? '',
            'adset', 'adgroup' => $dims['adgroup_id'] ?? '',
            default => $dims['campaign_id'] ?? '',
        };

        $date = $dims['stat_time_day'] ?? '';
        if ($date) {
            $date = substr($date, 0, 10);
        }

        return [
            'entity_id' => $entityId,
            'date_start' => $date,
            'date_stop' => $date,
            'impressions' => (int) ($metrics['impressions'] ?? 0),
            'clicks' => (int) ($metrics['clicks'] ?? 0),
            'spend' => (float) ($metrics['spend'] ?? 0),
            'spend_currency' => 'USD',
            'conversions' => (int) ($metrics['conversion'] ?? $metrics['complete_payment'] ?? 0),
            'revenue' => (float) ($metrics['total_complete_payment_rate'] ?? 0),
            'video_views' => (int) ($metrics['video_play_actions'] ?? 0),
            'reach' => (int) ($metrics['reach'] ?? 0),
            'raw_metrics' => $metrics,
        ];
    }

    private function resolveAdvertiserId(string $accountId): string
    {
        return config('ads_platforms.tiktok.advertiser_id') ?: $accountId;
    }
}
