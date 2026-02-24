<?php

namespace App\Infrastructure\Ads\Persistence;

use App\Domain\Ads\Ports\InsightStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\Models\AdsAd;
use App\Infrastructure\Ads\Persistence\Models\AdsAdSet;
use App\Infrastructure\Ads\Persistence\Models\AdsCampaign;
use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;

final class EloquentInsightStore implements InsightStorePort
{
    public function upsertCampaigns(Platform $platform, string $accountId, array $campaigns): void
    {
        foreach ($campaigns as $c) {
            AdsCampaign::updateOrCreate(
                [
                    'platform' => $platform->value,
                    'account_id' => $accountId,
                    'campaign_id' => $c['id'],
                ],
                [
                    'name' => $c['name'] ?? null,
                    'status' => $c['status'] ?? null,
                    'objective' => $c['objective'] ?? null,
                    'raw_data' => $c,
                ],
            );
        }
    }

    public function upsertAdSets(Platform $platform, string $accountId, array $adSets): void
    {
        foreach ($adSets as $a) {
            AdsAdSet::updateOrCreate(
                [
                    'platform' => $platform->value,
                    'account_id' => $accountId,
                    'ad_set_id' => $a['id'],
                ],
                [
                    'campaign_id' => $a['campaign_id'] ?? '',
                    'name' => $a['name'] ?? null,
                    'status' => $a['status'] ?? null,
                    'raw_data' => $a,
                ],
            );
        }
    }

    public function upsertAds(Platform $platform, string $accountId, array $ads): void
    {
        foreach ($ads as $a) {
            AdsAd::updateOrCreate(
                [
                    'platform' => $platform->value,
                    'account_id' => $accountId,
                    'ad_id' => $a['id'],
                ],
                [
                    'ad_set_id' => $a['adset_id'] ?? $a['ad_set_id'] ?? '',
                    'name' => $a['name'] ?? null,
                    'status' => $a['status'] ?? null,
                    'raw_data' => $a,
                ],
            );
        }
    }

    public function upsertInsights(Platform $platform, string $accountId, string $level, array $rows): void
    {
        foreach ($rows as $row) {
            $dateStart = is_string($row['date_start'])
                ? substr($row['date_start'], 0, 10)
                : $row['date_start']->format('Y-m-d');
            $dateStop = is_string($row['date_stop'])
                ? substr($row['date_stop'], 0, 10)
                : $row['date_stop']->format('Y-m-d');

            AdsInsightRow::updateOrCreate(
                [
                    'platform' => $platform->value,
                    'entity_id' => $row['entity_id'],
                    'date_start' => $dateStart,
                    'date_stop' => $dateStop,
                    'breakdown_hash' => $row['breakdown_hash'] ?? 'none',
                ],
                [
                    'account_id' => $accountId,
                    'level' => $level,
                    'impressions' => $row['impressions'] ?? 0,
                    'clicks' => $row['clicks'] ?? 0,
                    'spend' => $row['spend'] ?? 0,
                    'spend_currency' => $row['spend_currency'] ?? 'USD',
                    'conversions' => $row['conversions'] ?? 0,
                    'revenue' => $row['revenue'] ?? 0,
                    'video_views' => $row['video_views'] ?? 0,
                    'reach' => $row['reach'] ?? 0,
                    'raw_metrics' => $row['raw_metrics'] ?? null,
                ],
            );
        }
    }
}
