<?php

namespace App\Infrastructure\Ads\Meta;

use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\BaseAdsClient;

class MetaClient extends BaseAdsClient
{
    private string $apiVersion;

    private string $graphBaseUrl;

    public function __construct(
        protected readonly TokenStorePort $tokenStore,
    ) {
        $this->apiVersion = config('ads_platforms.meta.api_version', 'v22.0');
        $this->graphBaseUrl = config('ads_platforms.meta.base_url', 'https://graph.facebook.com');
    }

    protected function baseUrl(): string
    {
        return "{$this->graphBaseUrl}/{$this->apiVersion}";
    }

    protected function defaultHeaders(): array
    {
        return [];
    }

    public function getAccessToken(?string $accountId = null): string
    {
        $accountId = $accountId ?? config('ads_platforms.meta.ad_account_id');

        return $this->tokenStore->getAccessToken(Platform::Meta, $accountId)
            ?? config('ads_platforms.meta.access_token');
    }

    /**
     * Generic Graph API GET request with pagination support.
     */
    public function get(string $endpoint, array $params = [], ?string $accountId = null): array
    {
        $params['access_token'] = $this->getAccessToken($accountId);

        $this->logRequest('GET', $endpoint, ['params' => array_diff_key($params, ['access_token' => ''])]);

        return $this->http()->get($endpoint, $params)->throw()->json();
    }

    /**
     * POST request to Graph API.
     */
    public function post(string $endpoint, array $data = [], ?string $accountId = null): array
    {
        $data['access_token'] = $this->getAccessToken($accountId);

        $this->logRequest('POST', $endpoint);

        return $this->http()->post($endpoint, $data)->throw()->json();
    }

    /**
     * Paginate through cursor-based results from the Graph API.
     *
     * @return \Generator<array>
     */
    public function paginate(string $endpoint, array $params = [], ?string $accountId = null): \Generator
    {
        $params['access_token'] = $this->getAccessToken($accountId);
        $url = $endpoint;
        $isFirstRequest = true;

        do {
            if ($isFirstRequest) {
                $response = $this->http()->get($url, $params)->throw()->json();
                $isFirstRequest = false;
            } else {
                $response = $this->http()
                    ->withHeaders($this->defaultHeaders())
                    ->get($url)
                    ->throw()
                    ->json();
            }

            foreach ($response['data'] ?? [] as $item) {
                yield $item;
            }

            $url = $response['paging']['next'] ?? null;
        } while ($url);
    }

    /**
     * Submit an async insight report and poll until complete.
     */
    public function asyncInsightReport(string $objectId, array $params = [], ?string $accountId = null): array
    {
        $params['access_token'] = $this->getAccessToken($accountId);

        $response = $this->http()
            ->post("{$objectId}/insights", $params)
            ->throw()
            ->json();

        $reportRunId = $response['report_run_id'] ?? null;
        if (! $reportRunId) {
            return $response['data'] ?? [];
        }

        return $this->pollAsyncReport($reportRunId, $accountId);
    }

    private function pollAsyncReport(string $reportRunId, ?string $accountId): array
    {
        $token = $this->getAccessToken($accountId);
        $maxAttempts = 60;
        $attempt = 0;

        do {
            $attempt++;
            $waitSeconds = min(2 ** $attempt, 60);
            sleep($waitSeconds);

            $status = $this->http()
                ->get($reportRunId, ['access_token' => $token])
                ->throw()
                ->json();

            $state = $status['async_status'] ?? 'UNKNOWN';

            if ($state === 'Job Completed') {
                $results = [];
                foreach ($this->paginate("{$reportRunId}/insights", [], $accountId) as $row) {
                    $results[] = $row;
                }

                return $results;
            }

            if ($state === 'Job Failed') {
                $this->logError("Meta async report {$reportRunId} failed", $status);
                throw new \RuntimeException("Meta async insight report failed: {$reportRunId}");
            }
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException("Meta async insight report timed out: {$reportRunId}");
    }
}
