<?php

namespace App\Services\AI\Anthropic;

use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\Metadata;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use App\Services\AI\Contracts\AiTextProvider;
use App\Services\AI\Data\AiTextResponse;
use Generator;

class AnthropicTextProvider implements AiTextProvider
{
    public function __construct(
        private readonly AnthropicGateway $gateway,
    ) {}

    public function provider(): string
    {
        return 'anthropic';
    }

    public function defaultModel(): string
    {
        return (string) config('anthropic.model', 'claude-3-5-sonnet-latest');
    }

    public function createResponse(string $instructions, array $messages, array $metadata = [], array $options = []): AiTextResponse
    {
        $result = $this->gateway->messagesCreate(
            $this->buildArguments($instructions, $messages, $metadata, $options),
            $this->guardContext($metadata, $options)
        );

        $message = $result['message'];
        $usage = $message->usage;

        return new AiTextResponse(
            provider: 'anthropic',
            text: $this->extractText($message),
            model: (string) ($message->model ?? ($options['model'] ?? $this->defaultModel())),
            responseId: $message->id ?? null,
            promptTokens: $usage?->inputTokens,
            completionTokens: $usage?->outputTokens,
            totalTokens: ($usage?->inputTokens ?? 0) + ($usage?->outputTokens ?? 0),
            latencyMs: $result['latency_ms'] ?? null,
            requestId: $message->id ?? null,
            correlationId: $metadata['correlation_id'] ?? null,
            metadata: [
                'provider_response_id' => $message->id ?? null,
                'credential_source' => $result['credential_source'] ?? 'env_default',
            ],
        );
    }

    public function createStreamedResponse(string $instructions, array $messages, array $metadata = [], array $options = []): Generator
    {
        foreach (
            $this->gateway->messagesCreateStreamed(
                $this->buildArguments($instructions, $messages, $metadata, $options),
                $this->guardContext($metadata, $options)
            ) as $event
        ) {
            if (! $event instanceof RawContentBlockDeltaEvent) {
                continue;
            }

            if ($event->delta instanceof TextDelta) {
                yield $event->delta->text;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildArguments(string $instructions, array $messages, array $metadata, array $options): array
    {
        $arguments = [
            'maxTokens' => (int) ($options['max_output_tokens'] ?? config('anthropic.max_output_tokens', 1000)),
            'messages' => $this->normalizeMessages($messages),
            'model' => (string) ($options['model'] ?? $this->defaultModel()),
            'system' => $instructions,
            'temperature' => array_key_exists('temperature', $options)
                ? (float) $options['temperature']
                : (float) config('anthropic.temperature', 0.7),
        ];

        $userIdentifier = $this->makeUserIdentifier($metadata);
        if ($userIdentifier !== null) {
            $arguments['metadata'] = Metadata::with(userID: $userIdentifier);
        }

        if (isset($options['response_schema']) && is_array($options['response_schema'])) {
            $arguments['outputConfig'] = OutputConfig::with(
                format: JSONOutputFormat::with(schema: $options['response_schema'])
            );
        }

        return $arguments;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMessages(array $messages): array
    {
        return array_map(function (array $message): array {
            return [
                'role' => $message['role'],
                'content' => (string) ($message['content'] ?? ''),
            ];
        }, $messages);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function guardContext(array $metadata, array $options): array
    {
        return [
            'user_id' => $metadata['user_id'] ?? null,
            'section' => $metadata['section'] ?? null,
            'session_id' => $metadata['session_id'] ?? null,
            'service' => $metadata['service'] ?? 'anthropic.messages',
            'correlation_id' => $metadata['correlation_id'] ?? null,
            'api_key_override' => $options['api_key_override'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function makeUserIdentifier(array $metadata): ?string
    {
        $userId = $metadata['user_id'] ?? null;
        if ($userId === null) {
            return null;
        }

        $prefix = (string) config('anthropic.user_id_prefix', 'erp_user:');
        $hash = hash('sha256', implode('|', [
            $userId,
            $metadata['session_id'] ?? '',
            $metadata['section'] ?? 'general',
        ]));

        $maxHashLen = max(8, 64 - strlen($prefix));

        return $prefix . substr($hash, 0, $maxHashLen);
    }

    private function extractText(object $message): string
    {
        $chunks = [];

        foreach (($message->content ?? []) as $block) {
            if ($block instanceof TextBlock) {
                $chunks[] = $block->text;
                continue;
            }

            if (($block->type ?? '') === 'text') {
                $chunks[] = (string) ($block->text ?? '');
            }
        }

        return trim(implode('', $chunks));
    }
}
