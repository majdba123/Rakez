<?php

namespace App\Infrastructure\Ads;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseAdsClient
{
    protected int $maxRetries = 3;

    protected int $retryBaseMs = 1000;

    abstract protected function baseUrl(): string;

    abstract protected function defaultHeaders(): array;

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders(array_merge($this->defaultHeaders(), [
                'X-Correlation-ID' => (string) \Illuminate\Support\Str::uuid(),
            ]))
            ->timeout(30)
            ->retry(
                $this->maxRetries,
                fn (int $attempt, $e) => $this->retryDelayMs($attempt, $e),
                fn ($e) => $this->shouldRetry($e),
            );
    }

    protected function retryDelayMs(int $attempt, mixed $e): int
    {
        if ($e instanceof RequestException && $e->response) {
            $retryAfter = $e->response->header('Retry-After');
            if (is_numeric($retryAfter)) {
                return min((int) $retryAfter * 1000 + random_int(0, 250), 120_000);
            }
        }

        $base = $this->retryBaseMs * (2 ** max(0, $attempt - 1));

        return min($base + random_int(0, 250), 60_000);
    }

    protected function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof RequestException && $e->response) {
            $status = $e->response->status();
            if ($status === 429) {
                Log::warning('Ads API rate limited', [
                    'platform' => static::class,
                    'status' => 429,
                ]);

                return true;
            }

            return $status >= 500;
        }

        return true;
    }

    protected function logRequest(string $method, string $url, array $context = []): void
    {
        Log::debug("Ads API [{$method}] {$url}", $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        if (isset($context['body'])) {
            unset($context['body']);
        }
        Log::error("Ads API Error: {$message}", $context);
    }
}
