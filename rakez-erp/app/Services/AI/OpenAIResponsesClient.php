<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\AiAssistantException;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Responses\Responses\CreateResponse;
use Throwable;

class OpenAIResponsesClient
{
    public function __construct(
        private readonly AiOpenAiGateway $gateway,
    ) {}

    public function createResponse(string $instructions, array $messages, array $metadata = []): CreateResponse
    {
        $payload = $this->buildPayload($instructions, $messages, $metadata);
        $guardContext = [
            'user_id' => $metadata['user_id'] ?? null,
            'section' => $metadata['section'] ?? null,
            'session_id' => $metadata['session_id'] ?? null,
            'service' => $metadata['service'] ?? 'openai.responses',
            'correlation_id' => $metadata['correlation_id'] ?? null,
        ];

        return $this->gateway->responsesCreate($payload, $guardContext);
    }

    /**
     * Stream an OpenAI Responses API call, yielding text delta strings.
     *
     * @return Generator<int, string> Yields plain text chunks.
     */
    public function createStreamedResponse(string $instructions, array $messages, array $metadata = []): Generator
    {
        $payload = $this->buildPayload($instructions, $messages, $metadata);
        $guardContext = [
            'user_id' => $metadata['user_id'] ?? null,
            'section' => $metadata['section'] ?? null,
            'session_id' => $metadata['session_id'] ?? null,
            'service' => $metadata['service'] ?? 'openai.responses.stream',
            'correlation_id' => $metadata['correlation_id'] ?? null,
        ];

        try {
            foreach ($this->gateway->responsesCreateStreamed($payload, $guardContext) as $event) {
                if (isset($event->type) && $event->type === 'response.output_text.delta') {
                    yield $event->delta ?? '';
                }
            }
        } catch (Throwable $e) {
            throw $e instanceof AiAssistantException ? $e : $this->gateway->normalizeException($e);
        }
    }

    private function buildPayload(string $instructions, array $messages, array &$metadata): array
    {
        $metadata['correlation_id'] = $metadata['correlation_id'] ?? (string) Str::uuid();

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

        $hash = hash('sha256', "{$userId}|{$sessionId}|{$section}");

        $maxHashLen = 64 - strlen($prefix);
        if ($maxHashLen < 8) {
            return substr($hash, 0, 64);
        }

        return $prefix . substr($hash, 0, $maxHashLen);
    }
}
