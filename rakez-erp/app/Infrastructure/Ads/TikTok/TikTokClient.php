<?php

namespace App\Infrastructure\Ads\TikTok;

use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Promopult\TikTokMarketingApi\Client as TikTokSdkClient;
use Promopult\TikTokMarketingApi\Credentials;

/**
 * TikTok Ads API client using the Promopult TikTok Marketing API SDK.
 * Report, campaign, ad group and ad endpoints use the SDK; other requests use HTTP.
 */
class TikTokClient
{
    private string $apiBaseUrl;

    public function __construct(
        protected readonly TokenStorePort $tokenStore,
        protected readonly ?GuzzleClient $httpClient = null,
    ) {
        $this->apiBaseUrl = rtrim(config('ads_platforms.tiktok.base_url', 'https://business-api.tiktok.com/open_api/v1.3'), '/');
        if (! str_contains($this->apiBaseUrl, '/open_api/')) {
            $this->apiBaseUrl = 'https://business-api.tiktok.com';
        }
    }

    public function getAccessToken(string $accountId = ''): string
    {
        $token = $this->tokenStore->getAccessToken(Platform::TikTok, $accountId);

        return $token ?? config('ads_platforms.tiktok.access_token')
            ?? throw new \RuntimeException('No TikTok access token available');
    }

    /**
     * Build the SDK client for the given account (token from store or config).
     */
    private function sdkClient(string $accountId = ''): TikTokSdkClient
    {
        $token = $this->getAccessToken($accountId);
        $baseUrl = str_contains($this->apiBaseUrl, '/v1.3') ? 'https://business-api.tiktok.com' : $this->apiBaseUrl;
        $credentials = new Credentials($token, $baseUrl);
        $guzzle = $this->httpClient ?? new GuzzleClient(['timeout' => 30]);

        return new TikTokSdkClient($credentials, $guzzle);
    }

    /**
     * GET request. Uses SDK for report/campaign/adgroup/ad endpoints, HTTP otherwise.
     */
    public function get(string $endpoint, array $params = [], string $accountId = ''): array
    {
        $this->logRequest('GET', $endpoint, ['params' => $params]);

        $endpoint = str_replace(['/open_api/v1.3/', 'open_api/v1.3/'], '', $endpoint);
        $advertiserId = (int) ($params['advertiser_id'] ?? config('ads_platforms.tiktok.advertiser_id') ?: 0);
        if ($advertiserId === 0 && ! empty($accountId)) {
            $advertiserId = (int) $accountId;
        }

        $sdk = $this->sdkClient($accountId);

        if (str_contains($endpoint, 'report/integrated/get')) {
            $dimensions = $params['dimensions'] ?? [];
            $metrics = $params['metrics'] ?? null;
            if (is_string($dimensions)) {
                $dimensions = json_decode($dimensions, true) ?? [];
            }
            if (is_string($metrics)) {
                $metrics = json_decode($metrics, true) ?: null;
            }
            return $sdk->report->integratedGet(
                $advertiserId,
                $params['report_type'] ?? 'BASIC',
                $dimensions,
                $params['data_level'] ?? null,
                $params['service_type'] ?? null,
                $metrics,
                (bool) ($params['lifetime'] ?? false),
                $params['start_date'] ?? null,
                $params['end_date'] ?? null,
                $params['filters'] ?? null,
                $params['order_field'] ?? null,
                $params['order_type'] ?? null,
                isset($params['page']) ? (int) $params['page'] : null,
                isset($params['page_size']) ? (int) $params['page_size'] : null,
            );
        }

        if (str_contains($endpoint, 'campaign/get')) {
            return $sdk->campaign->get(
                $advertiserId,
                $params['fields'] ?? null,
                $params['filtering'] ?? null,
                isset($params['page']) ? (int) $params['page'] : null,
                isset($params['page_size']) ? (int) $params['page_size'] : null,
            );
        }

        if (str_contains($endpoint, 'adgroup/get')) {
            return $sdk->adGroup->get(
                $advertiserId,
                $params['fields'] ?? null,
                $params['filtering'] ?? null,
                isset($params['page']) ? (int) $params['page'] : null,
                isset($params['page_size']) ? (int) $params['page_size'] : null,
            );
        }

        if (str_contains($endpoint, 'ad/get')) {
            return $sdk->ad->getAds(
                $advertiserId,
                $params['fields'] ?? null,
                $params['filtering'] ?? null,
                isset($params['page']) ? (int) $params['page'] : null,
                isset($params['page_size']) ? (int) $params['page_size'] : null,
            );
        }

        return $this->httpGet($endpoint, $params, $accountId);
    }

    /**
     * Fallback HTTP GET for endpoints not covered by the SDK (e.g. v1.2 lead/get).
     */
    private function httpGet(string $endpoint, array $params, string $accountId): array
    {
        $token = $this->getAccessToken($accountId);
        $url = $this->apiBaseUrl . '/' . ltrim($endpoint, '/');
        if (str_starts_with($endpoint, 'lead/')) {
            $url = 'https://business-api.tiktok.com/open_api/v1.2/' . ltrim($endpoint, '/');
        }

        return Http::withHeaders(['Access-Token' => $token, 'Content-Type' => 'application/json'])
            ->timeout(30)
            ->get($url, $params)
            ->throw()
            ->json();
    }

    /**
     * POST request via HTTP (SDK used where applicable via get).
     */
    public function post(string $endpoint, array $data = [], string $accountId = ''): array
    {
        $token = $this->getAccessToken($accountId);
        $this->logRequest('POST', $endpoint);

        $url = $this->apiBaseUrl . '/' . ltrim($endpoint, '/');

        return Http::withHeaders(['Access-Token' => $token, 'Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($url, $data)
            ->throw()
            ->json();
    }

    /**
     * Paginate TikTok page-based results. Uses SDK-backed get() for supported endpoints.
     *
     * @return \Generator<array>
     */
    public function paginate(string $endpoint, array $params = [], string $accountId = '', int $pageSize = 100): \Generator
    {
        $page = 1;
        $params['page_size'] = $pageSize;

        do {
            $params['page'] = $page;
            $response = $this->get($endpoint, $params, $accountId);

            $data = $response['data'] ?? [];
            $list = $data['list'] ?? [];

            foreach ($list as $item) {
                yield $item;
            }

            $pageInfo = $data['page_info'] ?? [];
            $totalPages = (int) ceil(($pageInfo['total_number'] ?? 0) / $pageSize);
            $page++;
        } while ($page <= $totalPages && ! empty($list));
    }

    /**
     * Create an async reporting task and poll until complete (uses get for task/check).
     */
    public function asyncReport(array $params, string $accountId = ''): array
    {
        $response = $this->post('report/task/create/', $params, $accountId);

        $taskId = $response['data']['task_id'] ?? null;
        if (! $taskId) {
            throw new \RuntimeException('TikTok async report task creation failed: ' . json_encode($response));
        }

        return $this->pollReportTask($taskId, $params['advertiser_id'] ?? '', $accountId);
    }

    private function pollReportTask(string $taskId, string $advertiserId, string $accountId): array
    {
        $maxAttempts = 30;
        $attempt = 0;

        do {
            $attempt++;
            sleep(min(2 ** $attempt, 30));

            $response = $this->get('report/task/check/', [
                'task_id' => $taskId,
                'advertiser_id' => $advertiserId,
            ], $accountId);

            $status = $response['data']['status'] ?? 'UNKNOWN';

            if ($status === 'COMPLETED' || $status === 'SUCCESS') {
                return $response['data']['list'] ?? $response['data'] ?? [];
            }

            if (in_array($status, ['FAILED', 'CANCELLED'])) {
                throw new \RuntimeException("TikTok async report failed: {$taskId}");
            }
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException("TikTok async report timed out: {$taskId}");
    }

    private function logRequest(string $method, string $url, array $context = []): void
    {
        Log::debug("TikTok Ads API [{$method}] {$url}", $context);
    }
}
