<?php

namespace App\Infrastructure\Ads;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseAdsClient
{
    protected int $maxRetries = 3;

    protected int $retryBaseMs = 1000;

    abstract protected function baseUrl(): string;

    abstract protected function defaultHeaders(): array;

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->retry(
                $this->maxRetries,
                fn (int $attempt) => $this->retryBaseMs * (2 ** ($attempt - 1)),
                fn (\Exception $e, Response $response) => $this->shouldRetry($response),
            );
    }

    protected function shouldRetry(Response $response): bool
    {
        if ($response->status() === 429) {
            Log::warning('Ads API rate limited', [
                'platform' => static::class,
                'status' => 429,
            ]);

            return true;
        }

        return $response->serverError();
    }

    protected function logRequest(string $method, string $url, array $context = []): void
    {
        Log::debug("Ads API [{$method}] {$url}", $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::error("Ads API Error: {$message}", $context);
    }
}
