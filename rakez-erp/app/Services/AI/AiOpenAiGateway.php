<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Infrastructure\CircuitBreaker;
use App\Services\AI\Infrastructure\SmartRateLimiter;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingsApiResponse;
use OpenAI\Responses\Responses\CreateResponse;
use Throwable;

/**
 * Unified OpenAI entry point: circuit breaker, smart rate limits, retries,
 * correlation IDs, and stable error taxonomy for responses + embeddings.
 */
class AiOpenAiGateway
{
    public function __construct(
        private readonly ?CircuitBreaker $circuitBreaker = null,
        private readonly ?SmartRateLimiter $smartRateLimiter = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Full Responses API payload (may include metadata)
     * @param  array<string, mixed>  $guardContext  user_id, section, service, session_id, correlation_id
     */
    public function responsesCreate(array $payload, array $guardContext = []): CreateResponse
    {
        $payload = $this->ensureResponseMetadata($payload, $guardContext);
        $serviceName = (string) ($guardContext['service'] ?? $payload['metadata']['service'] ?? 'openai.responses');
        $user = $this->resolveUser($guardContext['user_id'] ?? null);

        $this->assertConfiguredForResponses($payload, $serviceName);
        $this->guardAvailability($serviceName, $user, $guardContext['section'] ?? null);

        $start = microtime(true);

        try {
            $response = $this->withRetry(fn () => OpenAI::responses()->create($payload));
            $this->markSuccess($serviceName);

            $requestId = $response->meta()->requestId ?? $response->id ?? null;
            Log::info('OpenAI gateway response ok', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'request_id' => $requestId,
                'model' => $payload['model'] ?? null,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'correlation_id' => $payload['metadata']['correlation_id'] ?? null,
                'service' => $serviceName,
            ]);

            return $response;
        } catch (Throwable $e) {
            $this->markFailure($serviceName);
            Log::warning('OpenAI gateway response failed', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $payload['model'] ?? null,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'correlation_id' => $payload['metadata']['correlation_id'] ?? null,
                'service' => $serviceName,
                'error' => $e->getMessage(),
            ]);

            throw $this->normalizeException($e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $guardContext
     * @return Generator<int, mixed>
     */
    public function responsesCreateStreamed(array $payload, array $guardContext = []): Generator
    {
        $payload = $this->ensureResponseMetadata($payload, $guardContext);
        $serviceName = (string) ($guardContext['service'] ?? $payload['metadata']['service'] ?? 'openai.responses.stream');
        $user = $this->resolveUser($guardContext['user_id'] ?? null);

        $this->assertConfiguredForResponses($payload, $serviceName);
        $this->guardAvailability($serviceName, $user, $guardContext['section'] ?? null);

        $start = microtime(true);

        try {
            $stream = OpenAI::responses()->createStreamed($payload);

            foreach ($stream as $event) {
                yield $event;
            }

            $this->markSuccess($serviceName);

            Log::info('OpenAI gateway stream ok', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $payload['model'] ?? null,
                'session_id' => $guardContext['session_id'] ?? null,
                'correlation_id' => $payload['metadata']['correlation_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->markFailure($serviceName);
            Log::warning('OpenAI gateway stream failed', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $payload['model'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $this->normalizeException($e);
        }
    }

    /**
     * @param  array<string, mixed>  $params  model, input, dimensions, etc.
     * @param  array<string, mixed>  $guardContext  optional user_id, section for rate limits
     */
    public function embeddingsCreate(array $params, array $guardContext = []): EmbeddingsApiResponse
    {
        $serviceName = 'openai.embeddings';
        $user = $this->resolveUser($guardContext['user_id'] ?? null);

        $this->assertConfiguredForEmbeddings($params, $serviceName);
        $this->guardAvailability($serviceName, $user, $guardContext['section'] ?? null);

        try {
            $response = $this->withRetry(fn () => OpenAI::embeddings()->create($params));
            $this->markSuccess($serviceName);

            return $response;
        } catch (Throwable $e) {
            $this->markFailure($serviceName);
            throw $this->normalizeException($e);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $guardContext
     */
    public function audioTranscribe(array $parameters, array $guardContext = []): TranscriptionResponse
    {
        $serviceName = (string) ($guardContext['service'] ?? 'openai.audio.transcribe');
        $user = $this->resolveUser($guardContext['user_id'] ?? null);

        $this->assertConfiguredForAudio($parameters, $serviceName, 'model');
        $this->guardAvailability($serviceName, $user, $guardContext['section'] ?? null);

        $start = microtime(true);

        try {
            $response = $this->withRetry(fn () => OpenAI::audio()->transcribe($parameters));
            $this->markSuccess($serviceName);

            Log::info('OpenAI gateway audio transcription ok', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $parameters['model'] ?? null,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'service' => $serviceName,
            ]);

            return $response;
        } catch (Throwable $e) {
            $this->markFailure($serviceName);
            Log::warning('OpenAI gateway audio transcription failed', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $parameters['model'] ?? null,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'service' => $serviceName,
                'error' => $e->getMessage(),
            ]);

            throw $this->normalizeException($e);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $guardContext
     */
    public function audioSpeech(array $parameters, array $guardContext = []): string
    {
        $serviceName = (string) ($guardContext['service'] ?? 'openai.audio.speech');
        $user = $this->resolveUser($guardContext['user_id'] ?? null);

        $this->assertConfiguredForAudio($parameters, $serviceName, 'model');
        $this->guardAvailability($serviceName, $user, $guardContext['section'] ?? null);

        $start = microtime(true);

        try {
            $response = $this->withRetry(fn () => OpenAI::audio()->speech($parameters));
            $this->markSuccess($serviceName);

            Log::info('OpenAI gateway audio speech ok', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $parameters['model'] ?? null,
                'voice' => $parameters['voice'] ?? null,
                'format' => $parameters['format'] ?? null,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'service' => $serviceName,
            ]);

            return $response;
        } catch (Throwable $e) {
            $this->markFailure($serviceName);
            Log::warning('OpenAI gateway audio speech failed', [
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'model' => $parameters['model'] ?? null,
                'voice' => $parameters['voice'] ?? null,
                'format' => $parameters['format'] ?? null,
                'session_id' => $guardContext['session_id'] ?? null,
                'section' => $guardContext['section'] ?? null,
                'service' => $serviceName,
                'error' => $e->getMessage(),
            ]);

            throw $this->normalizeException($e);
        }
    }

    public function normalizeException(Throwable $exception): AiAssistantException
    {
        if ($exception instanceof AiAssistantException) {
            return $exception;
        }

        $message = strtolower($exception->getMessage());

        if (str_contains($message, '429') || str_contains($message, 'rate limit')) {
            return new AiAssistantException('AI provider rate limit reached.', 'ai_rate_limited', 429);
        }

        if (
            str_contains($message, 'timeout') ||
            str_contains($message, '503') ||
            str_contains($message, '502') ||
            str_contains($message, 'temporarily unavailable')
        ) {
            return new AiAssistantException('AI provider is temporarily unavailable.', 'ai_provider_unavailable', 503);
        }

        if (str_contains($message, '400') && (str_contains($message, 'invalid') || str_contains($message, 'parameter'))) {
            return new AiAssistantException('AI request validation failed.', 'ai_validation_failed', 422);
        }

        return new AiAssistantException('AI request failed.', 'ai_provider_unavailable', 500);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $guardContext
     * @return array<string, mixed>
     */
    private function ensureResponseMetadata(array $payload, array $guardContext): array
    {
        $metadata = $payload['metadata'] ?? [];
        if (! is_array($metadata)) {
            $metadata = [];
        }

        $correlationId = $guardContext['correlation_id'] ?? $metadata['correlation_id'] ?? null;
        if ($correlationId === null || $correlationId === '') {
            $correlationId = (string) Str::uuid();
        }
        $metadata['correlation_id'] = (string) $correlationId;

        foreach ($metadata as $key => $value) {
            if ($value !== null && ! is_string($value)) {
                $metadata[$key] = (string) $value;
            }
        }

        $payload['metadata'] = $metadata;

        return $payload;
    }

    private function resolveUser(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertConfiguredForResponses(array $payload, string $serviceName): void
    {
        $this->assertOpenAiApiKeyConfigured($serviceName);
        $this->assertConfiguredModel($payload['model'] ?? null, $serviceName, 'responses model');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function assertConfiguredForEmbeddings(array $params, string $serviceName): void
    {
        $this->assertOpenAiApiKeyConfigured($serviceName);
        $this->assertConfiguredModel($params['model'] ?? null, $serviceName, 'embedding model');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function assertConfiguredForAudio(array $parameters, string $serviceName, string $modelKey): void
    {
        $this->assertOpenAiApiKeyConfigured($serviceName);
        $this->assertConfiguredModel($parameters[$modelKey] ?? null, $serviceName, 'audio model');
    }

    private function assertOpenAiApiKeyConfigured(string $serviceName): void
    {
        $apiKey = config('openai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '' || trim($apiKey) === 'test-fake-key-not-used') {
            Log::warning('OpenAI gateway misconfigured: missing API key', [
                'service' => $serviceName,
            ]);

            throw new AiAssistantException(
                'AI provider is not configured for this environment.',
                'ai_provider_misconfigured',
                503
            );
        }
    }

    private function assertConfiguredModel(mixed $model, string $serviceName, string $label): void
    {
        if (! is_string($model) || trim($model) === '') {
            Log::warning('OpenAI gateway misconfigured: missing model', [
                'service' => $serviceName,
                'label' => $label,
            ]);

            throw new AiAssistantException(
                'AI provider configuration is incomplete.',
                'ai_provider_misconfigured',
                503
            );
        }
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

    private function withRetry(callable $callback): mixed
    {
        $attempts = 0;

        $maxAttempts = (int) config('ai_assistant.retries.max_attempts', 3);
        $baseDelayMs = (int) config('ai_assistant.retries.base_delay_ms', 500);
        $maxDelayMs = (int) config('ai_assistant.retries.max_delay_ms', 5000);
        $jitterMs = (int) config('ai_assistant.retries.jitter_ms', 250);

        while (true) {
            $attempts++;

            try {
                return $callback();
            } catch (Throwable $exception) {
                $msg = strtolower($exception->getMessage());

                $retryable =
                    str_contains($msg, '429') ||
                    str_contains($msg, 'rate limit') ||
                    str_contains($msg, 'timeout') ||
                    str_contains($msg, 'temporarily unavailable') ||
                    str_contains($msg, '503') ||
                    str_contains($msg, '502');

                if (! $retryable || $attempts >= $maxAttempts) {
                    Log::warning('OpenAI gateway: no more retries', [
                        'attempts' => $attempts,
                        'error' => $exception->getMessage(),
                    ]);
                    throw $exception;
                }

                $delay = $baseDelayMs * (2 ** ($attempts - 1));
                $delay = min($delay, $maxDelayMs);

                $jitter = random_int(0, max(0, $jitterMs));
                usleep((int) (($delay + $jitter) * 1000));
            }
        }
    }
}
