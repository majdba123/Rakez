<?php

namespace App\Domain\Ads\Ports;

use App\Domain\Ads\ValueObjects\Platform;

interface InsightStorePort
{
    public function upsertCampaigns(Platform $platform, string $accountId, array $campaigns): void;

    public function upsertAdSets(Platform $platform, string $accountId, array $adSets): void;

    public function upsertAds(Platform $platform, string $accountId, array $ads): void;

    /**
     * Upsert insight rows (idempotent by platform+entity_id+date_start+date_stop+breakdown_hash).
     */
    public function upsertInsights(Platform $platform, string $accountId, string $level, array $rows): void;
}
