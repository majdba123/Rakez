<?php

namespace App\Infrastructure\Ads\Persistence;

use App\Domain\Ads\Ports\LeadStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\Models\AdsLeadSubmission;
use Carbon\Carbon;

final class EloquentLeadStore implements LeadStorePort
{
    public function upsertLeadSubmissions(Platform $platform, string $accountId, array $rows): array
    {
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $leadId = (string) ($row['lead_id'] ?? '');
            if ($leadId === '') {
                continue;
            }

            $createdTime = null;
            if (! empty($row['created_time'])) {
                try {
                    $createdTime = Carbon::parse($row['created_time']);
                } catch (\Throwable) {
                    $createdTime = null;
                }
            }

            $existing = AdsLeadSubmission::where('platform', $platform->value)
                ->where('account_id', $accountId)
                ->where('lead_id', $leadId)
                ->first();

            $payload = [
                'platform' => $platform->value,
                'account_id' => $accountId,
                'lead_id' => $leadId,
                'created_time' => $createdTime,
                'campaign_id' => $row['campaign_id'] ?? null,
                'adset_id' => $row['adset_id'] ?? null,
                'ad_id' => $row['ad_id'] ?? null,
                'form_id' => $row['form_id'] ?? null,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'extra_data' => $row['extra_data'] ?? null,
                'raw_payload' => $row['raw_payload'] ?? null,
                'synced_at' => now(),
            ];

            if (! $existing) {
                AdsLeadSubmission::create($payload);
                $created++;
                continue;
            }

            $existing->fill($payload);
            if ($existing->isDirty()) {
                $existing->save();
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}

