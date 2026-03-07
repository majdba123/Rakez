<?php

namespace App\Services\AI;

use Generator;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Throwable;

class OpenAIResponsesClient
{
    public function createResponse(string $instructions, array $messages, array $metadata = []): CreateResponse
    {
        $payload = $this->buildPayload($instructions, $messages, $metadata);

        $start = microtime(true);

        try {
            $response = $this->withRetry(fn () => OpenAI::responses()->create($payload));

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $requestId = $response->meta()->requestId ?? $response->id ?? null;

            Log::info('OpenAI response ok', [
                'latency_ms' => $latencyMs,
                'request_id' => $requestId,
                'model' => $payload['model'],
                'session_id' => $metadata['session_id'] ?? null,
                'section' => $metadata['section'] ?? null,
            ]);

            return $response;
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            Log::warning('OpenAI response failed', [
                'latency_ms' => $latencyMs,
                'model' => $payload['model'],
                'session_id' => $metadata['session_id'] ?? null,
                'section' => $metadata['section'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Stream an OpenAI Responses API call, yielding text delta strings.
     *
     * @return Generator<int, string> Yields plain text chunks.
     */
    public function createStreamedResponse(string $instructions, array $messages, array $metadata = []): Generator
    {
        $payload = $this->buildPayload($instructions, $messages, $metadata);

        $start = microtime(true);

        try {
            $stream = OpenAI::responses()->createStreamed($payload);

            foreach ($stream as $event) {
                if (isset($event->type) && $event->type === 'response.output_text.delta') {
                    yield $event->delta ?? '';
                }
            }

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            Log::info('OpenAI streamed response ok', [
                'latency_ms' => $latencyMs,
                'model' => $payload['model'],
                'session_id' => $metadata['session_id'] ?? null,
                'section' => $metadata['section'] ?? null,
            ]);
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            Log::warning('OpenAI streamed response failed', [
                'latency_ms' => $latencyMs,
                'model' => $payload['model'],
                'session_id' => $metadata['session_id'] ?? null,
                'section' => $metadata['section'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildPayload(string $instructions, array $messages, array &$metadata): array
    {
        $payload = [
            'model' => config('ai_assistant.openai.model', 'gpt-4.1-mini'),
            'instructions' => $instructions,
            'input' => $messages,
            'temperature' => (float) config('ai_assistant.openai.temperature', 0.7),
            'max_output_tokens' => (int) config('ai_assistant.openai.max_output_tokens', 1000),
            'truncation' => config('ai_assistant.openai.truncation', 'auto'),
            'metadata' => $metadata,
        ];

        $userId = $metadata['user_id'] ?? null;
        $sessionId = $metadata['session_id'] ?? null;
        $section = $metadata['section'] ?? 'general';

        if ($userId !== null) {
            $payload['safety_identifier'] = $this->makeSafetyIdentifier($userId, $sessionId, $section);
        }

        foreach ($metadata as $key => $value) {
            if ($value !== null && ! is_string($value)) {
                $metadata[$key] = (string) $value;
            }
        }
        $payload['metadata'] = $metadata;

        return $payload;
    }

    private function makeSafetyIdentifier($userId, $sessionId, $section): string
    {
        $prefix = config('ai_assistant.openai.safety_identifier_prefix', 'erp_user:');
        
        // Use a hash of the combined identifiers to stay within 64 chars
        $hash = hash('sha256', "{$userId}|{$sessionId}|{$section}");
        
        // Prefix + Hash should be kept within 64. 
        // If prefix is long, we truncate the hash.
        $maxHashLen = 64 - strlen($prefix);
        if ($maxHashLen < 8) {
            // Prefix too long, use only hash
            return substr($hash, 0, 64);
        }
        
        return $prefix . substr($hash, 0, $maxHashLen);
    }

    private function withRetry(callable $callback): CreateResponse
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

                // Treat common transient errors as retryable
                $retryable =
                    str_contains($msg, '429') ||
                    str_contains($msg, 'rate limit') ||
                    str_contains($msg, 'timeout') ||
                    str_contains($msg, 'temporarily unavailable') ||
                    str_contains($msg, '503') ||
                    str_contains($msg, '502');

                if (! $retryable || $attempts >= $maxAttempts) {
                    Log::warning('OpenAI response failed (no more retries)', [
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
