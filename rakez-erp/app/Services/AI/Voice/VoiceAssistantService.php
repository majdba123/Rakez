<?php

namespace App\Services\AI\Voice;

use App\Http\Middleware\RedactPiiFromAi;
use App\Models\AIConversation;
use App\Models\User;
use App\Services\AI\AIAssistantService;
use App\Services\AI\AiAuditService;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class VoiceAssistantService
{
    public function __construct(
        private readonly VoiceTranscriptionService $transcriptionService,
        private readonly VoiceSynthesisService $synthesisService,
        private readonly AIAssistantService $assistantService,
        private readonly AiAuditService $auditService,
        private readonly RedactPiiFromAi $piiRedactor,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(User $user, array $input): array
    {
        $this->ensureVoiceEnabled();

        $correlationId = (string) Str::uuid();
        $audio = $input['audio'] ?? null;
        $fallbackText = isset($input['fallback_text'])
            ? $this->piiRedactor->redact((string) $input['fallback_text'])
            : null;
        $section = $input['section'] ?? null;
        $sessionId = $input['session_id'] ?? null;
        $context = $input['context'] ?? [];
        $withTts = filter_var($input['with_tts'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $fallbackProvided = $fallbackText !== null && trim($fallbackText) !== '';

        $this->assistantService->ensureBudgetAvailable($user);

        if ($audio instanceof UploadedFile && ! $audio->isValid()) {
            throw new AiAssistantException('Uploaded audio is invalid.', 'ai_validation_failed', 422);
        }

        if ($audio instanceof UploadedFile) {
            $this->recordAudit($user, 'voice_audio_received', null, [
                'session_id' => $sessionId,
                'section' => $section,
                'mime_type' => $audio->getMimeType(),
                'size_bytes' => $audio->getSize(),
            ], [
                'received' => true,
                'persisted' => false,
            ], $correlationId);
        }

        $transcriptText = $fallbackText;
        $transcriptSource = $audio instanceof UploadedFile ? 'audio' : 'fallback_text';
        $transcriptionMeta = null;

        if ($audio instanceof UploadedFile) {
            try {
                $transcriptionMeta = $this->transcriptionService->transcribe($audio, [
                    'user_id' => $user->id,
                    'section' => $section,
                    'session_id' => $sessionId,
                    'service' => 'voice.transcription',
                    'correlation_id' => $correlationId,
                ]);
                $transcriptText = $this->piiRedactor->redact($transcriptionMeta['text']);

                $this->recordAudit($user, 'voice_transcript_generated', null, [
                    'session_id' => $sessionId,
                    'section' => $section,
                ], [
                    'language' => $transcriptionMeta['language'],
                    'duration' => $transcriptionMeta['duration'],
                    'transcript_preview' => mb_substr($transcriptText, 0, 200),
                ], $correlationId);
            } catch (AiAssistantException $exception) {
                if ($fallbackText === null || trim($fallbackText) === '') {
                    throw $exception;
                }

                $transcriptText = $fallbackText;
                $transcriptSource = 'fallback_text';

                $this->recordAudit($user, 'voice_transcription_failed_fallback_text', null, [
                    'session_id' => $sessionId,
                    'section' => $section,
                ], [
                    'fallback_used' => true,
                    'error_code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ], $correlationId);
            }
        }

        if ($transcriptText === null || trim($transcriptText) === '') {
            throw new AiAssistantException('No transcript or fallback text was available.', 'ai_validation_failed', 422);
        }

        $assistantPayload = $this->assistantService->chat(
            $transcriptText,
            $user,
            $sessionId,
            $section,
            $context,
            ['correlation_id' => $correlationId]
        );
        $assistantPayload['authoritative'] = true;

        $sessionId = $assistantPayload['session_id'];
        $this->annotateConversationMetadata($user, $sessionId, $assistantPayload['conversation_id'] ?? null, [
            'voice_input' => [
                'source' => $transcriptSource,
                'audio_uploaded' => $audio instanceof UploadedFile,
                'audio_persisted' => false,
                'audio_mime_type' => $audio instanceof UploadedFile ? $audio->getMimeType() : null,
                'transcription_language' => $transcriptionMeta['language'] ?? null,
                'transcription_duration' => $transcriptionMeta['duration'] ?? null,
                'fallback_text_provided' => $fallbackProvided,
                'fallback_text_used' => $transcriptSource === 'fallback_text',
                'correlation_id' => $correlationId,
            ],
        ]);

        $this->recordAudit($user, 'voice_assistant_response_generated', $assistantPayload['conversation_id'] ?? null, [
            'session_id' => $sessionId,
            'section' => $section,
            'transcript_source' => $transcriptSource,
            'fallback_text_used' => $transcriptSource === 'fallback_text',
        ], [
            'conversation_id' => $assistantPayload['conversation_id'] ?? null,
            'response_preview' => mb_substr((string) ($assistantPayload['message'] ?? ''), 0, 200),
            'authoritative' => true,
        ], $correlationId);

        $speech = [
            'requested' => $withTts,
            'generated' => false,
            'audio' => null,
            'error_code' => null,
        ];

        if ($withTts) {
            try {
                $speech['audio'] = $this->synthesisService->synthesize((string) $assistantPayload['message'], [
                    'voice' => $input['tts_voice'] ?? null,
                    'format' => $input['tts_format'] ?? null,
                ], [
                    'user_id' => $user->id,
                    'section' => $section,
                    'session_id' => $sessionId,
                    'service' => 'voice.tts',
                    'correlation_id' => $correlationId,
                ]);
                $speech['generated'] = true;

                $this->recordAudit($user, 'voice_tts_generated', $assistantPayload['conversation_id'] ?? null, [
                    'session_id' => $sessionId,
                    'section' => $section,
                ], [
                    'voice' => $speech['audio']['voice'],
                    'format' => $speech['audio']['format'],
                    'size_bytes' => $speech['audio']['size_bytes'],
                ], $correlationId);
            } catch (AiAssistantException $exception) {
                $speech['error_code'] = $exception->errorCode();

                $this->recordAudit($user, 'voice_tts_failed', $assistantPayload['conversation_id'] ?? null, [
                    'session_id' => $sessionId,
                    'section' => $section,
                ], [
                    'error_code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ], $correlationId);
            }
        }

        return [
            'input' => [
                'audio_uploaded' => $audio instanceof UploadedFile,
                'audio_persisted' => false,
                'fallback_text_provided' => $fallbackProvided,
                'fallback_text_used' => $transcriptSource === 'fallback_text',
            ],
            'transcript' => [
                'text' => $transcriptText,
                'source' => $transcriptSource,
                'language' => $transcriptionMeta['language'] ?? null,
                'duration' => $transcriptionMeta['duration'] ?? null,
                'fallback_text_used' => $transcriptSource === 'fallback_text',
            ],
            'assistant' => $assistantPayload,
            'speech' => $speech,
        ];
    }

    private function ensureVoiceEnabled(): void
    {
        if (! config('ai_voice.enabled', true)) {
            throw new AiAssistantException('Voice assistant is currently disabled.', 'ai_voice_disabled', 503);
        }
    }

    private function annotateConversationMetadata(User $user, string $sessionId, ?int $assistantConversationId, array $voiceMeta): void
    {
        $latestUserMessage = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('role', 'user')
            ->latest('id')
            ->first();

        if ($latestUserMessage) {
            $latestUserMessage->update([
                'metadata' => array_merge($latestUserMessage->metadata ?? [], $voiceMeta),
            ]);
        }

        if ($assistantConversationId) {
            $assistantMessage = AIConversation::query()
                ->where('user_id', $user->id)
                ->whereKey($assistantConversationId)
                ->first();

            if ($assistantMessage) {
                $assistantMessage->update([
                    'metadata' => array_merge($assistantMessage->metadata ?? [], [
                        'voice_reply' => [
                            'source_session' => $sessionId,
                            'correlation_id' => $voiceMeta['voice_input']['correlation_id'] ?? null,
                        ],
                    ]),
                ]);
            }
        }
    }

    private function recordAudit(
        User $user,
        string $action,
        ?int $resourceId,
        array $input,
        array $output,
        string $correlationId,
    ): void {
        $this->auditService->record(
            $user,
            $action,
            'assistant_session',
            $resourceId,
            $this->sanitizeForAudit($input),
            $this->sanitizeForAudit($output),
            $correlationId
        );
    }

    private function sanitizeForAudit(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->piiRedactor->redact($value);
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeForAudit($item);
            }

            return $sanitized;
        }

        return $value;
    }
}
