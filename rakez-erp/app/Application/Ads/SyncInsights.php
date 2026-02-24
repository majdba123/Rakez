<?php

namespace App\Application\Ads;

use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\Ports\InsightStorePort;
use App\Domain\Ads\ValueObjects\DateRange;

final class SyncInsights
{
    public function __construct(
        private readonly AdsReadPort $reader,
        private readonly InsightStorePort $store,
    ) {}

    /**
     * @param  string[]  $levels  e.g. ['campaign', 'adset', 'ad']
     */
    public function execute(string $accountId, DateRange $dateRange, array $levels = ['campaign']): void
    {
        $platform = $this->reader->platform();

        foreach ($levels as $level) {
            $rows = $this->reader->fetchInsights($accountId, $level, $dateRange);
            $this->store->upsertInsights($platform, $accountId, $level, $rows);
        }
    }
}
