<?php

namespace App\Domain\Ads\Ports;

use App\Domain\Ads\ValueObjects\DateRange;
use App\Domain\Ads\ValueObjects\Platform;

interface AdsReadPort
{
    public function platform(): Platform;

    /**
     * @return array<array{id: string, name: string, status: string, ...}>
     */
    public function listCampaigns(string $accountId): array;

    /**
     * @return array<array{id: string, name: string, status: string, campaign_id: string, ...}>
     */
    public function listAdSets(string $accountId): array;

    /**
     * @return array<array{id: string, name: string, status: string, adset_id: string, ...}>
     */
    public function listAds(string $accountId): array;

    /**
     * Fetch insights/stats for a given entity level and date range.
     *
     * @return array<array{entity_id: string, date_start: string, date_stop: string, ...metrics}>
     */
    public function fetchInsights(
        string $accountId,
        string $level,
        DateRange $dateRange,
        array $fields = [],
    ): array;
}
