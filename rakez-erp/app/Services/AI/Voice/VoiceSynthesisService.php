<?php

namespace App\Services\AI\Voice;

use App\Services\AI\AiOpenAiGateway;
use App\Services\AI\Exceptions\AiAssistantException;

class VoiceSynthesisService
{
    public function __construct(
        private readonly AiOpenAiGateway $gateway,
    ) {}

    /**
     * @return array{audio_base64: string, format: string, mime_type: string, size_bytes: int, voice: string}
     */
    public function synthesize(string $text, array $options = [], array $guardContext = []): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new AiAssistantException('Assistant response is empty and cannot be synthesized.', 'ai_validation_failed', 422);
        }

        $maxChars = (int) config('ai_voice.speech.max_input_characters', 2000);
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
        }

        $voice = (string) ($options['voice'] ?? config('ai_voice.speech.default_voice', 'alloy'));
        $format = (string) ($options['format'] ?? config('ai_voice.speech.default_format', 'mp3'));

        $binary = $this->gateway->audioSpeech([
            'model' => config('ai_voice.speech.model', 'gpt-4o-mini-tts'),
            'voice' => $voice,
            'input' => $text,
            'format' => $format,
        ], $guardContext);

        return [
            'audio_base64' => base64_encode($binary),
            'format' => $format,
            'mime_type' => $this->mimeTypeFor($format),
            'size_bytes' => strlen($binary),
            'voice' => $voice,
        ];
    }

    private function mimeTypeFor(string $format): string
    {
        return match ($format) {
            'wav' => 'audio/wav',
            'opus' => 'audio/opus',
            default => 'audio/mpeg',
        };
    }
}
