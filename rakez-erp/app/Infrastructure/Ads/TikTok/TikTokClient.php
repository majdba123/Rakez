<?php

namespace App\Infrastructure\Ads\TikTok;

use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\BaseAdsClient;

class TikTokClient extends BaseAdsClient
{
    private string $apiBaseUrl;

    public function __construct(
        protected readonly TokenStorePort $tokenStore,
    ) {
        $this->apiBaseUrl = config('ads_platforms.tiktok.base_url', 'https://business-api.tiktok.com/open_api/v1.3');
    }

    protected function baseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    protected function defaultHeaders(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    public function getAccessToken(string $accountId = ''): string
    {
        $token = $this->tokenStore->getAccessToken(Platform::TikTok, $accountId);

        return $token ?? config('ads_platforms.tiktok.access_token')
            ?? throw new \RuntimeException('No TikTok access token available');
    }

    /**
     * GET request with Access-Token header.
     */
    public function get(string $endpoint, array $params = [], string $accountId = ''): array
    {
        $token = $this->getAccessToken($accountId);

        $this->logRequest('GET', $endpoint, ['params' => $params]);

        return $this->http()
            ->withHeaders(['Access-Token' => $token])
            ->get($endpoint, $params)
            ->throw()
            ->json();
    }

    /**
     * POST request with Access-Token header.
     */
    public function post(string $endpoint, array $data = [], string $accountId = ''): array
    {
        $token = $this->getAccessToken($accountId);

        $this->logRequest('POST', $endpoint);

        return $this->http()
            ->withHeaders(['Access-Token' => $token])
            ->post($endpoint, $data)
            ->throw()
            ->json();
    }

    /**
     * Paginate TikTok page-based pagination.
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
     * Create an async reporting task and poll until complete.
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
}
