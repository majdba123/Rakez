<?php

namespace App\Infrastructure\Ads\Meta;

use Illuminate\Support\Carbon;

final class MetaLeadGenReader
{
    private const FIELDS = 'created_time,id,ad_id,adset_id,campaign_id,form_id,field_data';

    public function __construct(
        private readonly MetaClient $client,
    ) {}

    /**
     * Fetch leads for a Lead Gen form via Graph API Bulk Read.
     *
     * @return array<int, array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}>
     */
    public function fetchByFormId(string $formId, ?int $fromTimestamp = null, ?int $toTimestamp = null, ?string $accountId = null): array
    {
        $params = [
            'fields' => self::FIELDS,
        ];
        if ($fromTimestamp !== null || $toTimestamp !== null) {
            $filtering = [];
            if ($fromTimestamp !== null) {
                $filtering[] = [
                    'field' => 'time_created',
                    'operator' => 'GREATER_THAN_OR_EQUAL',
                    'value' => $fromTimestamp,
                ];
            }
            if ($toTimestamp !== null) {
                $filtering[] = [
                    'field' => 'time_created',
                    'operator' => 'LESS_THAN',
                    'value' => $toTimestamp,
                ];
            }
            $params['filtering'] = json_encode($filtering);
        }

        $leads = [];
        foreach ($this->client->paginate("{$formId}/leads", $params, $accountId) as $item) {
            $leads[] = $this->normalizeLead($item, $formId);
        }

        return $leads;
    }

    /**
     * Fetch leads for a specific ad.
     *
     * @return array<int, array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}>
     */
    public function fetchByAdId(string $adId, ?int $fromTimestamp = null, ?int $toTimestamp = null, ?string $accountId = null): array
    {
        $params = [
            'fields' => self::FIELDS,
        ];
        if ($fromTimestamp !== null || $toTimestamp !== null) {
            $filtering = [];
            if ($fromTimestamp !== null) {
                $filtering[] = [
                    'field' => 'time_created',
                    'operator' => 'GREATER_THAN_OR_EQUAL',
                    'value' => $fromTimestamp,
                ];
            }
            if ($toTimestamp !== null) {
                $filtering[] = [
                    'field' => 'time_created',
                    'operator' => 'LESS_THAN',
                    'value' => $toTimestamp,
                ];
            }
            $params['filtering'] = json_encode($filtering);
        }

        $leads = [];
        foreach ($this->client->paginate("{$adId}/leads", $params, $accountId) as $item) {
            $leads[] = $this->normalizeLead($item, $item['form_id'] ?? '');
        }

        return $leads;
    }

    /**
     * @param  array{id?: string, created_time?: string, ad_id?: string, adset_id?: string, campaign_id?: string, form_id?: string, field_data?: array<int, array{name?: string, values?: array}>}  $item
     * @return array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}
     */
    private function normalizeLead(array $item, string $formId): array
    {
        $fieldData = $item['field_data'] ?? [];
        $byName = [];
        foreach ($fieldData as $fd) {
            $name = $fd['name'] ?? '';
            $values = $fd['values'] ?? [];
            $byName[$name] = $values[0] ?? '';
        }

        $name = $this->extractName($byName);
        $email = $byName['email'] ?? '';
        $phone = $byName['phone_number'] ?? $byName['phone'] ?? '';

        $createdTime = $item['created_time'] ?? '';
        if ($createdTime && str_contains($createdTime, 'T')) {
            $createdTime = Carbon::parse($createdTime)->toIso8601String();
        }

        return [
            'platform' => 'meta',
            'lead_id' => (string) ($item['id'] ?? ''),
            'name' => $name,
            'email' => (string) $email,
            'phone' => (string) $phone,
            'form_id' => (string) ($item['form_id'] ?? $formId),
            'ad_id' => (string) ($item['ad_id'] ?? ''),
            'adset_id' => (string) ($item['adset_id'] ?? ''),
            'campaign_id' => (string) ($item['campaign_id'] ?? ''),
            'created_time' => $createdTime,
            'extra_data' => $byName,
            'raw_payload' => $item,
        ];
    }

    /**
     * @param  array<string, string>  $byName
     */
    private function extractName(array $byName): string
    {
        if (! empty($byName['full_name'])) {
            return $byName['full_name'];
        }
        $first = $byName['first_name'] ?? '';
        $last = $byName['last_name'] ?? '';

        return trim("{$first} {$last}");
    }
}
