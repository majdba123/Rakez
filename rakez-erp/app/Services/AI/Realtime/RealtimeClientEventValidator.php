<?php

namespace App\Services\AI\Realtime;

use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Support\Arr;

class RealtimeClientEventValidator
{
    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public function validate(string $type, array $event): array
    {
        return match ($type) {
            'input_audio_buffer.append' => $this->validateAudioAppend($event),
            'input_audio_buffer.commit' => $this->validateAudioCommit($event),
            'response.cancel' => $this->validateResponseCancel($event),
            'response.create' => $this->validateResponseCreate($event),
            'conversation.item.create' => $this->validateConversationItemCreate($event),
            default => throw new AiAssistantException('Unsupported realtime client event.', 'ai_realtime_validation_failed', 422),
        };
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function validateAudioAppend(array $event): array
    {
        $audio = $event['audio'] ?? null;

        if (! is_string($audio) || trim($audio) === '') {
            throw new AiAssistantException('Realtime audio append requires a base64 audio payload.', 'ai_realtime_validation_failed', 422);
        }

        $decoded = base64_decode($audio, true);
        if (! is_string($decoded)) {
            throw new AiAssistantException('Realtime audio append payload must be valid base64.', 'ai_realtime_validation_failed', 422);
        }

        $maxChunkBytes = (int) config('ai_realtime.transport.max_audio_append_bytes', 262144);
        if ($maxChunkBytes > 0 && strlen($decoded) > $maxChunkBytes) {
            throw new AiAssistantException('Realtime audio append payload exceeds the maximum allowed size.', 'ai_realtime_validation_failed', 422);
        }

        return [
            'type' => 'input_audio_buffer.append',
            'audio' => $audio,
            'meta' => [
                'audio_bytes' => strlen($decoded),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function validateAudioCommit(array $event): array
    {
        return [
            'type' => 'input_audio_buffer.commit',
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function validateResponseCancel(array $event): array
    {
        $responseId = $event['response_id'] ?? null;

        if ($responseId !== null && ! is_string($responseId)) {
            throw new AiAssistantException('Realtime response cancel uses an invalid response_id.', 'ai_realtime_validation_failed', 422);
        }

        return array_filter([
            'type' => 'response.cancel',
            'response_id' => $responseId,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function validateResponseCreate(array $event): array
    {
        $response = Arr::get($event, 'response', []);
        if (! is_array($response)) {
            throw new AiAssistantException('Realtime response.create requires a response object.', 'ai_realtime_validation_failed', 422);
        }

        $allowedKeys = ['instructions'];
        $unknownKeys = array_diff(array_keys($response), $allowedKeys);

        if ($unknownKeys !== []) {
            throw new AiAssistantException('Realtime response.create contains unsupported response options.', 'ai_realtime_validation_failed', 422);
        }

        $payload = [
            'type' => 'response.create',
        ];

        if (array_key_exists('instructions', $response)) {
            if (! is_string($response['instructions']) || trim($response['instructions']) === '') {
                throw new AiAssistantException('Realtime response.create instructions must be a non-empty string.', 'ai_realtime_validation_failed', 422);
            }

            $payload['response'] = [
                'instructions' => $response['instructions'],
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function validateConversationItemCreate(array $event): array
    {
        $item = $event['item'] ?? null;
        if (! is_array($item)) {
            throw new AiAssistantException('Realtime conversation.item.create requires an item object.', 'ai_realtime_validation_failed', 422);
        }

        $type = $item['type'] ?? null;
        if ($type !== 'message') {
            throw new AiAssistantException('Realtime conversation.item.create currently supports message items only.', 'ai_realtime_validation_failed', 422);
        }

        $role = $item['role'] ?? null;
        if (! in_array($role, ['user', 'assistant', 'system'], true)) {
            throw new AiAssistantException('Realtime conversation.item.create requires a valid role.', 'ai_realtime_validation_failed', 422);
        }

        $content = $item['content'] ?? null;
        if (! is_array($content) || $content === []) {
            throw new AiAssistantException('Realtime conversation.item.create requires content blocks.', 'ai_realtime_validation_failed', 422);
        }

        foreach ($content as $block) {
            if (! is_array($block)) {
                throw new AiAssistantException('Realtime conversation.item.create contains an invalid content block.', 'ai_realtime_validation_failed', 422);
            }

            $blockType = $block['type'] ?? null;
            if (! in_array($blockType, ['input_text', 'text'], true)) {
                throw new AiAssistantException('Realtime conversation.item.create only supports text content blocks.', 'ai_realtime_validation_failed', 422);
            }

            $text = $block['text'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                throw new AiAssistantException('Realtime conversation.item.create text blocks require non-empty text.', 'ai_realtime_validation_failed', 422);
            }
        }

        $payload = [
            'type' => 'conversation.item.create',
            'item' => [
                'type' => 'message',
                'role' => $role,
                'content' => $content,
            ],
        ];

        $this->ensurePayloadSizeWithinLimit($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensurePayloadSizeWithinLimit(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $limit = (int) config('ai_realtime.transport.max_client_event_payload_bytes', 524288);

        if (! is_string($json)) {
            throw new AiAssistantException('Realtime event payload could not be encoded.', 'ai_realtime_validation_failed', 422);
        }

        if ($limit > 0 && strlen($json) > $limit) {
            throw new AiAssistantException('Realtime event payload exceeds the maximum allowed size.', 'ai_realtime_validation_failed', 422);
        }
    }
}
