<?php

namespace App\Services\AI\Voice;

use App\Services\AI\AiOpenAiGateway;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Http\UploadedFile;

class VoiceTranscriptionService
{
    public function __construct(
        private readonly AiOpenAiGateway $gateway,
    ) {}

    /**
     * @return array{text: string, language: ?string, duration: ?float}
     */
    public function transcribe(UploadedFile $audio, array $guardContext = []): array
    {
        $handle = fopen($audio->getRealPath(), 'r');

        if ($handle === false) {
            throw new AiAssistantException('Uploaded audio could not be read.', 'ai_validation_failed', 422);
        }

        try {
            $response = $this->gateway->audioTranscribe([
                'model' => config('ai_voice.transcription.model', 'gpt-4o-mini-transcribe'),
                'file' => $handle,
                'language' => config('ai_voice.transcription.language', 'ar'),
                'prompt' => config('ai_voice.transcription.prompt'),
            ], $guardContext);
        } finally {
            fclose($handle);
        }

        $text = trim($response->text ?? '');
        if ($text === '') {
            throw new AiAssistantException('Speech transcription returned an empty transcript.', 'ai_provider_unavailable', 503);
        }

        $maxChars = (int) config('ai_voice.transcription.max_transcript_characters', 2000);
        if (mb_strlen($text) > $maxChars) {
            throw new AiAssistantException('Speech transcript is too long for this MVP endpoint.', 'ai_validation_failed', 422);
        }

        return [
            'text' => $text,
            'language' => $response->language,
            'duration' => $response->duration,
        ];
    }
}
