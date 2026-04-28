<?php

namespace App\Domain\Ads\Ports;

use App\Domain\Ads\ValueObjects\Platform;

interface LeadStorePort
{
    /**
     * @param  array<int, array{
     *   lead_id: string,
     *   created_time?: string,
     *   campaign_id?: string,
     *   adset_id?: string,
     *   ad_id?: string,
     *   form_id?: string,
     *   name?: string,
     *   email?: string,
     *   phone?: string,
     *   extra_data?: array,
     *   raw_payload?: array
     * }>  $rows
     * @return array{created: int, updated: int}
     */
    public function upsertLeadSubmissions(Platform $platform, string $accountId, array $rows): array;
}

