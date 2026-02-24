<?php

namespace App\Application\Ads;

use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\Ports\InsightStorePort;

final class SyncCampaignStructure
{
    public function __construct(
        private readonly AdsReadPort $reader,
        private readonly InsightStorePort $store,
    ) {}

    public function execute(string $accountId): void
    {
        $platform = $this->reader->platform();

        $campaigns = $this->reader->listCampaigns($accountId);
        $this->store->upsertCampaigns($platform, $accountId, $campaigns);

        $adSets = $this->reader->listAdSets($accountId);
        $this->store->upsertAdSets($platform, $accountId, $adSets);

        $ads = $this->reader->listAds($accountId);
        $this->store->upsertAds($platform, $accountId, $ads);
    }
}
