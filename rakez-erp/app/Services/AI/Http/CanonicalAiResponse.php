<?php

namespace App\Services\AI\Http;

use Illuminate\Http\JsonResponse;

class CanonicalAiResponse
{
    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $executionMeta
     * @return array<string, mixed>
     */
    public function successEnvelope(
        array $result,
        array $executionMeta = [],
        ?string $conversationId = null,
        string $locale = 'en',
        string $inputMode = 'text'
    ): array {
        return [
            'success' => true,
            'data' => $this->successData($result, $executionMeta, $conversationId, $locale, $inputMode),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $executionMeta
     * @return array<string, mixed>
     */
    public function successData(
        array $result,
        array $executionMeta = [],
        ?string $conversationId = null,
        string $locale = 'en',
        string $inputMode = 'text'
    ): array {
        $answerMarkdown = (string) ($result['answer_markdown'] ?? '');

        return [
            'conversation_id' => $conversationId,
            'answer' => $answerMarkdown,
            'answer_markdown' => $answerMarkdown,
            'sources' => array_values((array) ($result['sources'] ?? [])),
            'links' => array_values((array) ($result['links'] ?? [])),
            'suggested_actions' => array_values((array) ($result['suggested_actions'] ?? [])),
            'follow_up_questions' => array_values((array) ($result['follow_up_questions'] ?? [])),
            'meta' => [
                'locale' => $locale,
                'input_mode' => $inputMode,
                'model' => $executionMeta['model'] ?? null,
                'tokens' => $executionMeta['total_tokens'] ?? null,
                'latency_ms' => $executionMeta['latency_ms'] ?? null,
                'correlation_id' => $executionMeta['correlation_id'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public function errorEnvelope(string $code, string $message, array $details = []): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function errorJson(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json($this->errorEnvelope($code, $message, $details), $status);
    }
}
