<?php

namespace App\Services\AI\Anthropic;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\Message;
use Anthropic\RequestOptions;
use App\Models\User;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Infrastructure\CircuitBreaker;
use App\Services\AI\Infrastructure\SmartRateLimiter;
use Generator;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnthropicGateway
{
    public function __construct(
        private readonly AnthropicCredentialResolver $credentialResolver,
        private readonly ?CircuitBreaker $circuitBreaker = null,
        private readonly ?SmartRateLimiter $smartRateLimiter = null,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $guardContext
     * @return array{message: Message, latency_ms: int, credential_source: string}
     */
    public function messagesCreate(array $arguments, array $guardContext = []): array
    {
        $serviceName = (string) ($guardContext['service'] ?? 'anthropic.messages');
        $user = $this->resolveUser($guardContext['user_id'] ?? null);
        $model = (string) ($arguments['model'] ?? '');

        $this->assertEnabled($serviceName);
        $this->assertConfiguredModel($model, $serviceName);
        $this->guardAvailability($serviceName, $user, $guardContext['section'] ?? null);

        $resolved = $this->credentialResolver->resolve($guardContext['api_key_override'] ?? null);
        $client = $this->makeClient($resolved['api_key']);
        $start = microtime(true);

        try {
            /** @var Message $message */
            $message = $client->messages->create(...$this->withRequestOptions($arguments));
            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $this->markSuccess($serviceName);

            Log::info('Anthropic gateway response ok', [
                'provider' => 'anthropic',
                'credential_source' => $resolved['source'],
                'latency_ms' => $latencyMs,
                'request_id' => $message->id ?? null,
                'model' => $model,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'correlation_id' => $guardContext['correlation_id'] ?? null,
                'service' => $serviceName,
            ]);

            return [
                'message' => $message,
                'latency_ms' => $latencyMs,
                'credential_source' => $resolved['source'],
            ];
        } catch (Throwable $exception) {
            $this->markFailure($serviceName);

            Log::warning('Anthropic gateway response failed', [
                'provider' => 'anthropic',
                'credential_source' => $resolved['source'],
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $model,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'correlation_id' => $guardContext['correlation_id'] ?? null,
                'service' => $serviceName,
                'error' => $this->sanitizeError($exception->getMessage()),
            ]);

            throw $this->normalizeException($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $guardContext
     * @return Generator<int, mixed>
     */
    public function messagesCreateStreamed(array $arguments, array $guardContext = []): Generator
    {
        $serviceName = (string) ($guardContext['service'] ?? 'anthropic.messages.stream');
        $user = $this->resolveUser($guardContext['user_id'] ?? null);
        $model = (string) ($arguments['model'] ?? '');

        $this->assertEnabled($serviceName);
        $this->assertConfiguredModel($model, $serviceName);
        $this->guardAvailability($serviceName, $user, $guardContext['section'] ?? null);

        $resolved = $this->credentialResolver->resolve($guardContext['api_key_override'] ?? null);
        $client = $this->makeClient($resolved['api_key']);
        $start = microtime(true);

        try {
            $stream = $client->messages->createStream(...$this->withRequestOptions($arguments));

            foreach ($stream as $event) {
                yield $event;
            }

            $this->markSuccess($serviceName);

            Log::info('Anthropic gateway stream ok', [
                'provider' => 'anthropic',
                'credential_source' => $resolved['source'],
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $model,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'correlation_id' => $guardContext['correlation_id'] ?? null,
                'service' => $serviceName,
            ]);
        } catch (Throwable $exception) {
            $this->markFailure($serviceName);

            Log::warning('Anthropic gateway stream failed', [
                'provider' => 'anthropic',
                'credential_source' => $resolved['source'],
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $model,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'correlation_id' => $guardContext['correlation_id'] ?? null,
                'service' => $serviceName,
                'error' => $this->sanitizeError($exception->getMessage()),
            ]);

            throw $this->normalizeException($exception);
        }
    }

    public function normalizeException(Throwable $exception): AiAssistantException
    {
        if ($exception instanceof AiAssistantException) {
            return $exception;
        }

        return match (true) {
            $exception instanceof RateLimitException => new AiAssistantException(
                'AI provider rate limit reached.',
                'ai_rate_limited',
                429
            ),
            $exception instanceof AuthenticationException,
            $exception instanceof PermissionDeniedException => new AiAssistantException(
                'AI provider is not configured for this environment.',
                'ai_provider_misconfigured',
                503
            ),
            $exception instanceof BadRequestException => new AiAssistantException(
                'AI request validation failed.',
                'ai_validation_failed',
                422
            ),
            $exception instanceof APITimeoutException,
            $exception instanceof APIConnectionException,
            $exception instanceof InternalServerException,
            $exception instanceof APIStatusException => new AiAssistantException(
                'AI provider is temporarily unavailable.',
                'ai_provider_unavailable',
                503
            ),
            default => new AiAssistantException(
                'AI request failed.',
                'ai_provider_unavailable',
                500
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function withRequestOptions(array $arguments): array
    {
        $arguments['requestOptions'] = RequestOptions::parse(
            $arguments['requestOptions'] ?? null,
            RequestOptions::with(
                timeout: (float) config('anthropic.timeout', 30),
                maxRetries: max(0, (int) config('anthropic.retries.max_attempts', 3) - 1),
                initialRetryDelay: (float) config('anthropic.retries.initial_delay_seconds', 0.5),
                maxRetryDelay: (float) config('anthropic.retries.max_delay_seconds', 8.0),
            ),
        );

        return $arguments;
    }

    private function makeClient(string $apiKey): Client
    {
        return new Client(
            apiKey: $apiKey,
            baseUrl: config('anthropic.base_url'),
        );
    }

    private function assertEnabled(string $serviceName): void
    {
        if (! config('anthropic.enabled', false)) {
            Log::warning('Anthropic gateway misconfigured: provider disabled', [
                'service' => $serviceName,
            ]);

            throw new AiAssistantException(
                'AI provider is not configured for this environment.',
                'ai_provider_misconfigured',
                503
            );
        }
    }

    private function assertConfiguredModel(?string $model, string $serviceName): void
    {
        if (! is_string($model) || trim($model) === '') {
            Log::warning('Anthropic gateway misconfigured: missing model', [
                'service' => $serviceName,
            ]);

            throw new AiAssistantException(
                'AI provider configuration is incomplete.',
                'ai_provider_misconfigured',
                503
            );
        }
    }

    private function resolveUser(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }

    private function guardAvailability(string $serviceName, ?User $user, ?string $section): void
    {
        if ($this->circuitBreaker && ! $this->circuitBreaker->isAvailable($serviceName)) {
            throw new AiAssistantException(
                'AI provider is temporarily unavailable. Please retry shortly.',
                'ai_provider_unavailable',
                503
            );
        }

        if ($user && $this->smartRateLimiter && ! $this->smartRateLimiter->check($user, $section)) {
            throw new AiAssistantException(
                'AI request limit exceeded for this user.',
                'ai_rate_limited',
                429
            );
        }
    }

    private function markSuccess(string $serviceName): void
    {
        $this->circuitBreaker?->recordSuccess($serviceName);
    }

    private function markFailure(string $serviceName): void
    {
        $this->circuitBreaker?->recordFailure($serviceName);
    }

    private function sanitizeError(string $message): string
    {
        $message = preg_replace('/sk-ant-[A-Za-z0-9\-_]+/', '[REDACTED_ANTHROPIC_KEY]', $message) ?? $message;

        return preg_replace('/sk-[A-Za-z0-9\-_]+/', '[REDACTED_API_KEY]', $message) ?? $message;
    }
}
