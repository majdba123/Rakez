<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiTextProvider;
use App\Services\AI\Data\AiTextResponse;
use Generator;

class OpenAiTextProvider implements AiTextProvider
{
    public function __construct(
        private readonly OpenAIResponsesClient $client,
    ) {}

    public function provider(): string
    {
        return 'openai';
    }

    public function defaultModel(): string
    {
        return (string) config('ai_assistant.openai.model', 'gpt-4.1-mini');
    }

    public function createResponse(string $instructions, array $messages, array $metadata = [], array $options = []): AiTextResponse
    {
        $response = $this->client->createResponse($instructions, $messages, $metadata, $options);
        $usage = $response->usage;
        $meta = $response->meta();

        return new AiTextResponse(
            provider: 'openai',
            text: $this->extractText($response),
            model: (string) ($response->model ?? ($options['model'] ?? $this->defaultModel())),
            responseId: $response->id ?? null,
            promptTokens: $usage?->inputTokens,
            completionTokens: $usage?->outputTokens,
            totalTokens: $usage?->totalTokens,
            latencyMs: $meta->openai->processingMs ?? null,
            requestId: $meta->requestId ?? null,
            correlationId: $response->metadata['correlation_id'] ?? ($metadata['correlation_id'] ?? null),
            metadata: [
                'provider_response_id' => $response->id ?? null,
            ],
        );
    }

    public function createStreamedResponse(string $instructions, array $messages, array $metadata = [], array $options = []): Generator
    {
        yield from $this->client->createStreamedResponse($instructions, $messages, $metadata, $options);
    }

    private function extractText(object $response): string
    {
        if (isset($response->output) && is_array($response->output)) {
            foreach ($response->output as $output) {
                if (($output->type ?? '') !== 'message' || ($output->role ?? '') !== 'assistant') {
                    continue;
                }

                foreach (($output->content ?? []) as $content) {
                    if (($content->type ?? '') === 'output_text') {
                        return (string) ($content->text ?? '');
                    }
                }
            }
        }

        return (string) ($response->outputText ?? '');
    }
}
