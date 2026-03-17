<?php

namespace App\Infrastructure\Ads\TikTok;

use Illuminate\Support\Facades\Http;

/**
 * Fetches leads from TikTok Lead Generation API (open_api/v1.2/lead/get/).
 * Uses v1.2 base URL for lead endpoint; token from TikTokClient/config.
 */
final class TikTokLeadGenReader
{
    private const LEAD_API_BASE = 'https://business-api.tiktok.com/open_api/v1.2';

    public function __construct(
        private readonly TikTokClient $client,
    ) {}

    /**
     * Fetch leads for an advertiser. Optional date filter if API supports it.
     *
     * @return array<int, array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}>
     */
    public function fetchLeads(string $advertiserId, ?string $dateFrom = null, ?string $dateTo = null, string $accountId = ''): array
    {
        $token = $this->client->getAccessToken($accountId);
        $leads = [];
        $page = 1;
        $pageSize = 100;

        do {
            $params = [
                'advertiser_id' => $advertiserId,
                'page' => $page,
                'page_size' => $pageSize,
            ];

            $response = Http::baseUrl(self::LEAD_API_BASE)
                ->withHeaders(['Access-Token' => $token, 'Content-Type' => 'application/json'])
                ->timeout(30)
                ->get('lead/get/', $params)
                ->throw()
                ->json();

            $data = $response['data'] ?? [];
            $list = $data['list'] ?? $data['leads'] ?? [];

            foreach ($list as $item) {
                $leads[] = $this->normalizeLead($item);
            }

            $pageInfo = $data['page_info'] ?? [];
            $totalNumber = (int) ($pageInfo['total_number'] ?? 0);
            $totalPages = $pageSize > 0 ? (int) ceil($totalNumber / $pageSize) : 0;
            $page++;
        } while ($page <= $totalPages && ! empty($list));

        // Optional client-side date filter if API does not support it
        if ($dateFrom !== null || $dateTo !== null) {
            $fromTs = $dateFrom ? strtotime($dateFrom) : 0;
            $toTs = $dateTo ? strtotime($dateTo . ' 23:59:59') : PHP_INT_MAX;
            $leads = array_values(array_filter($leads, function (array $row) use ($fromTs, $toTs): bool {
                $t = $row['created_time'] ? strtotime($row['created_time']) : 0;
                return $t >= $fromTs && $t <= $toTs;
            }));
        }

        return $leads;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}
     */
    private function normalizeLead(array $item): array
    {
        $createdTime = $item['create_time'] ?? $item['created_time'] ?? '';
        if (is_numeric($createdTime)) {
            $createdTime = date('Y-m-d\TH:i:s\Z', (int) $createdTime);
        }

        $extra = $item;
        unset($extra['create_time'], $extra['created_time'], $extra['id'], $extra['lead_id']);

        return [
            'platform' => 'tiktok',
            'lead_id' => (string) ($item['id'] ?? $item['lead_id'] ?? ''),
            'name' => (string) ($item['name'] ?? $item['full_name'] ?? $item['user_name'] ?? ''),
            'email' => (string) ($item['email'] ?? ''),
            'phone' => (string) ($item['phone'] ?? $item['phone_number'] ?? ''),
            'form_id' => (string) ($item['form_id'] ?? ''),
            'ad_id' => (string) ($item['ad_id'] ?? ''),
            'adset_id' => (string) ($item['adset_id'] ?? $item['adgroup_id'] ?? ''),
            'campaign_id' => (string) ($item['campaign_id'] ?? ''),
            'created_time' => $createdTime,
            'extra_data' => $extra,
        ];
    }
}
