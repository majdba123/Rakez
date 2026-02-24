<?php

namespace App\Infrastructure\Ads\Meta;

use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\ValueObjects\DateRange;
use App\Domain\Ads\ValueObjects\Platform;

final class MetaInsightsReader implements AdsReadPort
{
    public function __construct(
        private readonly MetaClient $client,
    ) {}

    public function platform(): Platform
    {
        return Platform::Meta;
    }

    public function listCampaigns(string $accountId): array
    {
        $fields = 'id,name,status,objective,daily_budget,lifetime_budget,start_time,stop_time';
        $campaigns = [];

        foreach ($this->client->paginate("act_{$accountId}/campaigns", [
            'fields' => $fields,
            'limit' => 500,
        ], $accountId) as $item) {
            $campaigns[] = [
                'id' => $item['id'],
                'name' => $item['name'] ?? '',
                'status' => $item['status'] ?? '',
                'objective' => $item['objective'] ?? '',
            ];
        }

        return $campaigns;
    }

    public function listAdSets(string $accountId): array
    {
        $fields = 'id,name,status,campaign_id,daily_budget,lifetime_budget,targeting,optimization_goal';
        $adSets = [];

        foreach ($this->client->paginate("act_{$accountId}/adsets", [
            'fields' => $fields,
            'limit' => 500,
        ], $accountId) as $item) {
            $adSets[] = [
                'id' => $item['id'],
                'name' => $item['name'] ?? '',
                'status' => $item['status'] ?? '',
                'campaign_id' => $item['campaign_id'] ?? '',
            ];
        }

        return $adSets;
    }

    public function listAds(string $accountId): array
    {
        $fields = 'id,name,status,adset_id,creative{id,name,thumbnail_url}';
        $ads = [];

        foreach ($this->client->paginate("act_{$accountId}/ads", [
            'fields' => $fields,
            'limit' => 500,
        ], $accountId) as $item) {
            $ads[] = [
                'id' => $item['id'],
                'name' => $item['name'] ?? '',
                'status' => $item['status'] ?? '',
                'adset_id' => $item['adset_id'] ?? '',
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
        if (empty($fields)) {
            $fields = $this->defaultInsightFields();
        }

        $params = [
            'fields' => implode(',', $fields),
            'level' => $level,
            'time_range' => json_encode($dateRange->toMetaTimeRange()),
            'time_increment' => 1,
            'limit' => 500,
        ];

        $rows = [];
        foreach ($this->client->paginate("act_{$accountId}/insights", $params, $accountId) as $item) {
            $rows[] = $this->normalizeInsightRow($item, $level);
        }

        return $rows;
    }

    private function defaultInsightFields(): array
    {
        return [
            'campaign_id', 'campaign_name',
            'adset_id', 'adset_name',
            'ad_id', 'ad_name',
            'impressions', 'clicks', 'spend',
            'actions', 'action_values',
            'reach', 'video_30_sec_watched_actions',
            'date_start', 'date_stop',
        ];
    }

    private function normalizeInsightRow(array $item, string $level): array
    {
        $entityId = match ($level) {
            'ad' => $item['ad_id'] ?? '',
            'adset' => $item['adset_id'] ?? '',
            default => $item['campaign_id'] ?? '',
        };

        $conversions = 0;
        $revenue = 0.0;
        foreach ($item['actions'] ?? [] as $action) {
            if (in_array($action['action_type'] ?? '', ['offsite_conversion.fb_pixel_purchase', 'purchase', 'omni_purchase'])) {
                $conversions += (int) ($action['value'] ?? 0);
            }
        }
        foreach ($item['action_values'] ?? [] as $av) {
            if (in_array($av['action_type'] ?? '', ['offsite_conversion.fb_pixel_purchase', 'purchase', 'omni_purchase'])) {
                $revenue += (float) ($av['value'] ?? 0);
            }
        }

        $videoViews = 0;
        foreach ($item['video_30_sec_watched_actions'] ?? [] as $v) {
            $videoViews += (int) ($v['value'] ?? 0);
        }

        return [
            'entity_id' => $entityId,
            'date_start' => $item['date_start'] ?? '',
            'date_stop' => $item['date_stop'] ?? '',
            'impressions' => (int) ($item['impressions'] ?? 0),
            'clicks' => (int) ($item['clicks'] ?? 0),
            'spend' => (float) ($item['spend'] ?? 0),
            'spend_currency' => 'USD',
            'conversions' => $conversions,
            'revenue' => $revenue,
            'video_views' => $videoViews,
            'reach' => (int) ($item['reach'] ?? 0),
            'raw_metrics' => $item,
        ];
    }
}
