<?php

namespace App\Infrastructure\Ads\Snap;

use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\BaseAdsClient;

class SnapClient extends BaseAdsClient
{
    private string $adsBaseUrl;

    public function __construct(
        protected readonly TokenStorePort $tokenStore,
    ) {
        $this->adsBaseUrl = config('ads_platforms.snap.ads_base_url', 'https://adsapi.snapchat.com/v1');
    }

    protected function baseUrl(): string
    {
        return $this->adsBaseUrl;
    }

    protected function defaultHeaders(): array
    {
        return [];
    }

    public function getAccessToken(string $accountId): string
    {
        return $this->tokenStore->getAccessToken(Platform::Snap, $accountId)
            ?? throw new \RuntimeException('No Snap access token available');
    }

    /**
     * GET with Bearer auth and automatic next_link pagination.
     */
    public function get(string $endpoint, array $params = [], string $accountId = ''): array
    {
        $token = $this->getAccessToken($accountId);

        $this->logRequest('GET', $endpoint, ['params' => $params]);

        return $this->http()
            ->withToken($token)
            ->get($endpoint, $params)
            ->throw()
            ->json();
    }

    /**
     * Paginate using Snap's next_link cursor pattern.
     *
     * @return \Generator<array>
     */
    public function paginate(string $endpoint, array $params = [], string $accountId = '', int $limit = 200): \Generator
    {
        $params['limit'] = $limit;
        $token = $this->getAccessToken($accountId);
        $url = $endpoint;
        $isFirstRequest = true;

        do {
            if ($isFirstRequest) {
                $response = $this->http()->withToken($token)->get($url, $params)->throw()->json();
                $isFirstRequest = false;
            } else {
                $response = $this->http()->withToken($token)->get($url)->throw()->json();
            }

            $dataKeys = array_filter(array_keys($response), fn ($k) => ! in_array($k, ['paging', 'request_status', 'request_id']));
            $dataKey = reset($dataKeys) ?: 'data';

            foreach ($response[$dataKey] ?? [] as $wrapper) {
                $innerKeys = array_keys($wrapper);
                $innerKey = reset($innerKeys);
                yield $wrapper[$innerKey] ?? $wrapper;
            }

            $url = $response['paging']['next_link'] ?? null;
        } while ($url);
    }

    /**
     * Fetch stats with optional async mode.
     */
    public function fetchStats(
        string $entityType,
        string $entityId,
        array $params,
        string $accountId = '',
        bool $async = false,
    ): array {
        if ($async) {
            $params['async'] = 'true';
        }

        $endpoint = "{$entityType}/{$entityId}/stats";
        $response = $this->get($endpoint, $params, $accountId);

        if ($async && isset($response['report_run_id'])) {
            return $this->pollAsyncStats($response['report_run_id'], $accountId);
        }

        return $response;
    }

    private function pollAsyncStats(string $reportRunId, string $accountId): array
    {
        $maxAttempts = 30;
        $attempt = 0;

        do {
            $attempt++;
            sleep(min(2 ** $attempt, 30));

            $response = $this->get('stats_report', ['report_run_id' => $reportRunId], $accountId);

            $status = $response['report_status'] ?? 'UNKNOWN';
            if ($status === 'COMPLETED') {
                $downloadUrl = $response['download_link'] ?? null;
                if ($downloadUrl) {
                    $token = $this->getAccessToken($accountId);

                    return $this->http()->withToken($token)->get($downloadUrl)->throw()->json();
                }

                return $response;
            }

            if (in_array($status, ['FAILED', 'CANCELLED'])) {
                throw new \RuntimeException("Snap async stats report failed: {$reportRunId}");
            }
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException("Snap async stats report timed out: {$reportRunId}");
    }
}
