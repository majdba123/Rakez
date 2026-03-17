<?php

namespace App\Services\Ads;

use App\Models\Lead;

/**
 * Syncs normalized platform lead rows into the CRM leads table.
 * Skips rows that already exist (same campaign_platform + platform_lead_id).
 */
final class PlatformLeadSyncService
{
    /**
     * @param  array<int, array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, campaign_id: string, created_time: string}>  $rows
     * @return array{created: int, skipped: int}
     */
    public function sync(array $rows): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $platform = $row['platform'] ?? '';
            $leadId = $row['lead_id'] ?? '';
            if ($platform === '' || $leadId === '') {
                $skipped++;
                continue;
            }

            $exists = Lead::where('campaign_platform', $platform)
                ->where('platform_lead_id', $leadId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $contactInfo = $row['phone'] ?? $row['email'] ?? '';
            if ($contactInfo === '' && ! empty($row['email'])) {
                $contactInfo = $row['email'];
            }

            Lead::create([
                'name' => $row['name'] ?? 'Unknown',
                'contact_info' => $contactInfo,
                'source' => 'ads_' . $platform,
                'campaign_platform' => $platform,
                'campaign_id' => $row['campaign_id'] ?? null,
                'platform_lead_id' => $leadId,
                'status' => 'new',
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
