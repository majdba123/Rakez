<?php

namespace App\Infrastructure\Ads\Snap;

use Illuminate\Support\Carbon;

/**
 * Fetches lead submissions from Snapchat Marketing API (lead generation forms).
 * Paths default to documented plural resources; override via config if Snap changes API shape.
 *
 * @see https://docs.snap.com/api/marketing-api/Ads-API/lead-generation-ads
 */
final class SnapLeadGenReader
{
    public function __construct(
        private readonly SnapClient $client,
    ) {}

    /**
     * @return array<int, array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}>
     */
    public function fetchLeads(string $adAccountId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $formsPath = str_replace(
            '{ad_account_id}',
            $adAccountId,
            config('ads_platforms.snap.lead_forms_list_path', 'adaccounts/{ad_account_id}/lead_generation_forms'),
        );

        $leads = [];

        foreach ($this->client->paginate($formsPath, [], $adAccountId) as $wrapper) {
            $form = $wrapper['lead_generation_form'] ?? $wrapper['adform'] ?? $wrapper;
            $formId = (string) ($form['id'] ?? '');
            if ($formId === '') {
                continue;
            }

            $leadsPath = str_replace(
                '{lead_form_id}',
                $formId,
                config('ads_platforms.snap.leads_for_form_path', 'lead_generation_forms/{lead_form_id}/leads'),
            );

            foreach ($this->client->paginate($leadsPath, [], $adAccountId) as $leadWrap) {
                $lead = $leadWrap['lead'] ?? $leadWrap['lead_generation_form_submission'] ?? $leadWrap;
                $leads[] = $this->normalizeLead($lead, $formId);
            }
        }

        if ($dateFrom !== null || $dateTo !== null) {
            $fromTs = $dateFrom ? strtotime($dateFrom) : 0;
            $toTs = $dateTo ? strtotime($dateTo.' 23:59:59') : PHP_INT_MAX;
            $leads = array_values(array_filter($leads, function (array $row) use ($fromTs, $toTs): bool {
                $t = $row['created_time'] !== '' ? strtotime($row['created_time']) : 0;

                return $t >= $fromTs && $t <= $toTs;
            }));
        }

        return $leads;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}
     */
    private function normalizeLead(array $item, string $formId): array
    {
        $byKey = [];
        foreach ($item['fields'] ?? $item['answers'] ?? [] as $field) {
            if (is_string($field)) {
                continue;
            }
            $key = $field['field_type'] ?? $field['name'] ?? $field['key'] ?? '';
            $val = $field['value'] ?? $field['field_value'] ?? ($field['values'][0] ?? '');
            if ($key !== '') {
                $byKey[(string) $key] = is_scalar($val) ? (string) $val : json_encode($val);
            }
        }

        $name = $this->buildName($byKey, $item);
        $email = $byKey['EMAIL'] ?? $byKey['email'] ?? $item['email'] ?? '';
        $phone = $byKey['PHONE_NUMBER'] ?? $byKey['phone'] ?? $item['phone'] ?? '';

        $created = $item['created_at'] ?? $item['created_time'] ?? $item['submission_time'] ?? '';
        if (is_numeric($created)) {
            $created = Carbon::createFromTimestamp((int) $created)->toIso8601String();
        } elseif (is_string($created) && $created !== '') {
            $created = Carbon::parse($created)->toIso8601String();
        } else {
            $created = '';
        }

        $extra = $item;
        unset($extra['fields'], $extra['answers']);

        return [
            'platform' => 'snap',
            'lead_id' => (string) ($item['id'] ?? $item['submission_id'] ?? ''),
            'name' => $name,
            'email' => (string) $email,
            'phone' => (string) $phone,
            'form_id' => (string) ($item['lead_generation_form_id'] ?? $formId),
            'ad_id' => (string) ($item['ad_id'] ?? ''),
            'adset_id' => (string) ($item['ad_squad_id'] ?? $item['adsquad_id'] ?? ''),
            'campaign_id' => (string) ($item['campaign_id'] ?? ''),
            'created_time' => $created,
            'extra_data' => array_merge($byKey, $extra),
            'raw_payload' => $item,
        ];
    }

    /**
     * @param  array<string, string>  $byKey
     */
    private function buildName(array $byKey, array $item): string
    {
        if (! empty($byKey['FULL_NAME'])) {
            return $byKey['FULL_NAME'];
        }
        $first = $byKey['FIRST_NAME'] ?? '';
        $last = $byKey['LAST_NAME'] ?? '';
        $combined = trim("{$first} {$last}");
        if ($combined !== '') {
            return $combined;
        }

        return (string) ($item['name'] ?? $item['full_name'] ?? 'Unknown');
    }
}
