<?php

namespace App\Infrastructure\Ads\Meta;

use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use FacebookAds\Api;
use FacebookAds\Http\RequestInterface;
use FacebookAds\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta (Facebook) Ads API client using the official Facebook PHP Business SDK.
 * All requests go through the SDK (facebook/php-business-sdk).
 */
class MetaClient
{
    private string $apiVersion;

    public function __construct(
        protected readonly TokenStorePort $tokenStore,
    ) {
        $this->apiVersion = config('ads_platforms.meta.api_version', 'v22.0');
    }

    public function getAccessToken(?string $accountId = null): string
    {
        $accountId = $accountId ?? config('ads_platforms.meta.ad_account_id');

        return $this->tokenStore->getAccessToken(Platform::Meta, $accountId)
            ?? config('ads_platforms.meta.access_token');
    }

    /**
     * Ensure the SDK Api is initialized with the given account's token.
     */
    private function apiForAccount(?string $accountId = null): Api
    {
        $appId = config('ads_platforms.meta.app_id');
        $appSecret = config('ads_platforms.meta.app_secret');
        $token = $this->getAccessToken($accountId);

        if (! $appId || ! $appSecret) {
            throw new \RuntimeException('META_APP_ID and META_APP_SECRET are required to use the Meta Business SDK.');
        }

        $session = new Session($appId, $appSecret, $token);
        $current = Api::instance();

        if ($current === null) {
            Api::init($appId, $appSecret, $token, false);

            return Api::instance();
        }

        return $current->getCopyWithSession($session);
    }

    /**
     * GET request via the Meta Business SDK.
     */
    public function get(string $endpoint, array $params = [], ?string $accountId = null): array
    {
        $api = $this->apiForAccount($accountId);
        $this->logRequest('GET', $endpoint, ['params' => array_diff_key($params, ['access_token' => ''])]);

        $request = $api->prepareRequest($endpoint, RequestInterface::METHOD_GET, $params);
        $response = $api->executeRequest($request);

        if ($response->getStatusCode() >= 400) {
            $body = $response->getContent();
            Log::error('Meta Ads API Error', [
                'endpoint' => $endpoint,
                'status' => $response->getStatusCode(),
                'body' => $body,
            ]);
            throw new \RuntimeException('Meta API error: ' . ($body['error']['message'] ?? $response->getBody()));
        }

        return $response->getContent() ?? [];
    }

    /**
     * POST request via the Meta Business SDK.
     */
    public function post(string $endpoint, array $data = [], ?string $accountId = null): array
    {
        $api = $this->apiForAccount($accountId);
        $this->logRequest('POST', $endpoint);

        $request = $api->prepareRequest($endpoint, RequestInterface::METHOD_POST, $data);
        $response = $api->executeRequest($request);

        if ($response->getStatusCode() >= 400) {
            $body = $response->getContent();
            Log::error('Meta Ads API Error', [
                'endpoint' => $endpoint,
                'status' => $response->getStatusCode(),
                'body' => $body,
            ]);
            throw new \RuntimeException('Meta API error: ' . ($body['error']['message'] ?? $response->getBody()));
        }

        return $response->getContent() ?? [];
    }

    /**
     * Paginate through cursor-based results. First page via SDK, next pages via next_link.
     *
     * @return \Generator<array>
     */
    public function paginate(string $endpoint, array $params = [], ?string $accountId = null): \Generator
    {
        $params['limit'] = $params['limit'] ?? 500;
        $url = $endpoint;
        $isFirst = true;
        $token = $this->getAccessToken($accountId);

        do {
            if ($isFirst) {
                $response = $this->get($url, $params, $accountId);
                $isFirst = false;
            } else {
                // Meta paging "next" links may or may not include an access_token.
                // Ensure we can follow pages reliably without depending on upstream echoing tokens.
                if (! str_contains($url, 'access_token=')) {
                    $url .= (str_contains($url, '?') ? '&' : '?') . 'access_token=' . urlencode($token);
                }

                $response = Http::timeout(30)
                    ->retry(3, 1000, function ($e, $request) {
                        $status = property_exists($e, 'response') && $e->response ? $e->response->status() : null;
                        return $status === 429 || ($status !== null && $status >= 500);
                    })
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
     * Submit an async insight report and poll until complete (via SDK).
     */
    public function asyncInsightReport(string $objectId, array $params = [], ?string $accountId = null): array
    {
        $api = $this->apiForAccount($accountId);
        $endpoint = $objectId . '/insights';
        $this->logRequest('POST', $endpoint);

        $request = $api->prepareRequest($endpoint, RequestInterface::METHOD_POST, $params);
        $response = $api->executeRequest($request);
        $content = $response->getContent();

        $reportRunId = $content['report_run_id'] ?? null;
        if (! $reportRunId) {
            return $content['data'] ?? [];
        }

        return $this->pollAsyncReport($reportRunId, $accountId);
    }

    private function pollAsyncReport(string $reportRunId, ?string $accountId): array
    {
        $api = $this->apiForAccount($accountId);
        $maxAttempts = 60;
        $attempt = 0;

        do {
            $attempt++;
            $waitSeconds = min(2 ** $attempt, 60);
            sleep($waitSeconds);

            $request = $api->prepareRequest($reportRunId, RequestInterface::METHOD_GET, []);
            $response = $api->executeRequest($request);
            $status = $response->getContent();

            $state = $status['async_status'] ?? 'UNKNOWN';

            if ($state === 'Job Completed') {
                $results = [];
                foreach ($this->paginate($reportRunId . '/insights', [], $accountId) as $row) {
                    $results[] = $row;
                }
                return $results;
            }

            if ($state === 'Job Failed') {
                Log::error("Meta async report {$reportRunId} failed", $status);
                throw new \RuntimeException("Meta async insight report failed: {$reportRunId}");
            }
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException("Meta async insight report timed out: {$reportRunId}");
    }

    private function logRequest(string $method, string $url, array $context = []): void
    {
        Log::debug("Meta Ads API [{$method}] {$url}", $context);
    }
}
