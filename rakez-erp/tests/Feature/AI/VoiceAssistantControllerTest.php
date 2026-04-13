<?php

namespace Tests\Feature\AI;

use App\Models\AIConversation;
use App\Models\AiAuditEntry;
use App\Models\User;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Voice\VoiceSynthesisService;
use App\Services\AI\Voice\VoiceTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Mockery;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VoiceAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('use-ai-assistant', 'web');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_voice_chat_endpoint_validates_audio_mime_type(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    }

    public function test_voice_chat_endpoint_validates_audio_size_limit(): void
    {
        config(['ai_voice.upload.max_kb' => 8]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('large.wav', 16, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    }

    public function test_voice_chat_rejects_tts_options_when_tts_is_not_requested(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->post('/api/ai/voice/chat', [
            'fallback_text' => 'Hello',
            'tts_voice' => 'alloy',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tts_voice']);
    }

    public function test_voice_chat_returns_error_when_transcription_fails_without_fallback_text(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->once()
            ->andThrow(new AiAssistantException('AI provider is temporarily unavailable.', 'ai_provider_unavailable', 503));
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('clip.wav', 256, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'ai_provider_unavailable');
    }

    public function test_voice_chat_returns_503_when_voice_feature_is_disabled_before_provider_calls(): void
    {
        config(['ai_voice.enabled' => false]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldNotReceive('transcribe');
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        OpenAI::fake();

        $response = $this->post('/api/ai/voice/chat', [
            'fallback_text' => 'Use this text instead',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'ai_voice_disabled');

        OpenAI::assertNotSent(\OpenAI\Resources\Responses::class);
        OpenAI::assertNotSent(\OpenAI\Resources\Audio::class);
    }

    public function test_voice_chat_falls_back_to_text_when_transcription_fails(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->once()
            ->andThrow(new AiAssistantException('AI provider is temporarily unavailable.', 'ai_provider_unavailable', 503));
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        OpenAI::fake([
            $this->fakeResponse('Fallback text answer'),
        ]);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('clip.wav', 256, 'audio/wav'),
            'fallback_text' => 'Use this text instead',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.input.audio_uploaded', true)
            ->assertJsonPath('data.input.audio_persisted', false)
            ->assertJsonPath('data.input.fallback_text_used', true)
            ->assertJsonPath('data.transcript.source', 'fallback_text')
            ->assertJsonPath('data.transcript.fallback_text_used', true)
            ->assertJsonPath('data.transcript.text', 'Use this text instead')
            ->assertJsonPath('data.assistant.message', 'Fallback text answer')
            ->assertJsonPath('data.assistant.authoritative', true);

        $this->assertDatabaseHas('ai_audit_trail', [
            'user_id' => $user->id,
            'action' => 'voice_transcription_failed_fallback_text',
        ]);
    }

    public function test_voice_chat_preserves_session_continuity(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->twice()
            ->andReturn(
                ['text' => 'First spoken turn', 'language' => 'ar', 'duration' => 2.1],
                ['text' => 'Second spoken turn', 'language' => 'ar', 'duration' => 1.8],
            );
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        OpenAI::fake([
            $this->fakeResponse('First answer'),
            $this->fakeResponse('Second answer'),
        ]);

        $sessionId = '22222222-2222-2222-2222-222222222222';

        $first = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('first.wav', 128, 'audio/wav'),
            'session_id' => $sessionId,
        ], ['Accept' => 'application/json']);

        $second = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('second.wav', 128, 'audio/wav'),
            'session_id' => $sessionId,
        ], ['Accept' => 'application/json']);

        $first->assertOk()->assertJsonPath('data.assistant.session_id', $sessionId);
        $second->assertOk()->assertJsonPath('data.assistant.session_id', $sessionId);

        $this->assertSame(4, AIConversation::query()->where('session_id', $sessionId)->count());

        $latestUserTurn = AIConversation::query()
            ->where('session_id', $sessionId)
            ->where('role', 'user')
            ->latest('id')
            ->first();

        $this->assertNotNull($latestUserTurn);
        $this->assertSame('audio', $latestUserTurn->metadata['voice_input']['source'] ?? null);
        $this->assertSame('ar', $latestUserTurn->metadata['voice_input']['transcription_language'] ?? null);
    }

    public function test_voice_chat_can_generate_optional_tts_payload(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->once()
            ->andReturn(['text' => 'Read this aloud', 'language' => 'ar', 'duration' => 1.4]);
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        $synthesis = Mockery::mock(VoiceSynthesisService::class);
        $synthesis->shouldReceive('synthesize')
            ->once()
            ->andReturn([
                'audio_base64' => base64_encode('fake-audio'),
                'format' => 'mp3',
                'mime_type' => 'audio/mpeg',
                'size_bytes' => 10,
                'voice' => 'alloy',
            ]);
        $this->app->instance(VoiceSynthesisService::class, $synthesis);

        OpenAI::fake([
            $this->fakeResponse('Spoken answer'),
        ]);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('tts.wav', 128, 'audio/wav'),
            'with_tts' => true,
            'tts_voice' => 'alloy',
            'tts_format' => 'mp3',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.assistant.message', 'Spoken answer')
            ->assertJsonPath('data.assistant.authoritative', true)
            ->assertJsonPath('data.speech.requested', true)
            ->assertJsonPath('data.speech.generated', true)
            ->assertJsonPath('data.speech.audio.format', 'mp3')
            ->assertJsonPath('data.speech.audio.voice', 'alloy');
    }

    public function test_voice_chat_keeps_text_response_when_tts_generation_fails(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->once()
            ->andReturn(['text' => 'Read this aloud', 'language' => 'ar', 'duration' => 1.4]);
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        $synthesis = Mockery::mock(VoiceSynthesisService::class);
        $synthesis->shouldReceive('synthesize')
            ->once()
            ->andThrow(new AiAssistantException('AI provider is temporarily unavailable.', 'ai_provider_unavailable', 503));
        $this->app->instance(VoiceSynthesisService::class, $synthesis);

        OpenAI::fake([
            $this->fakeResponse('Text survives TTS failure'),
        ]);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('tts.wav', 128, 'audio/wav'),
            'with_tts' => true,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.assistant.message', 'Text survives TTS failure')
            ->assertJsonPath('data.assistant.authoritative', true)
            ->assertJsonPath('data.speech.requested', true)
            ->assertJsonPath('data.speech.generated', false)
            ->assertJsonPath('data.speech.error_code', 'ai_provider_unavailable');

        $this->assertDatabaseHas('ai_audit_trail', [
            'user_id' => $user->id,
            'action' => 'voice_tts_failed',
        ]);
    }

    public function test_voice_chat_success_records_complete_redacted_audit_trail_without_raw_pii_leakage(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->once()
            ->andReturn([
                'text' => 'اتصل على 0512345678 للعميل',
                'language' => 'ar',
                'duration' => 1.4,
            ]);
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        OpenAI::fake([
            $this->fakeResponse('Redacted answer'),
        ]);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('clip.wav', 128, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.input.audio_persisted', false)
            ->assertJsonPath('data.transcript.fallback_text_used', false)
            ->assertJsonPath('data.transcript.text', 'اتصل على [REDACTED_PHONE] للعميل')
            ->assertJsonPath('data.transcript.source', 'audio')
            ->assertJsonPath('data.assistant.message', 'Redacted answer')
            ->assertJsonPath('data.assistant.authoritative', true);

        $entries = AiAuditEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('action', [
                'voice_audio_received',
                'voice_transcript_generated',
                'voice_assistant_response_generated',
            ])
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $entries);
        $this->assertNotNull($entries->first()->correlation_id);
        $this->assertSame(1, $entries->pluck('correlation_id')->unique()->count());

        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('0512345678', (string) $entry->input_summary);
            $this->assertStringNotContainsString('0512345678', (string) $entry->output_summary);
        }

        $userTurn = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('role', 'user')
            ->latest('id')
            ->first();

        $this->assertNotNull($userTurn);
        $this->assertSame('اتصل على [REDACTED_PHONE] للعميل', $userTurn->message);
    }

    public function test_voice_chat_returns_429_when_budget_is_exhausted_before_openai_call(): void
    {
        config(['ai_assistant.budgets.per_user_daily_tokens' => 100]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => '33333333-3333-3333-3333-333333333333',
            'role' => 'assistant',
            'message' => 'Prior response',
            'total_tokens' => 100,
        ]);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldNotReceive('transcribe');
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        OpenAI::fake();

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('clip.wav', 128, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(429)
            ->assertJsonPath('error_code', 'ai_budget_exceeded');

        OpenAI::assertNotSent(\OpenAI\Resources\Responses::class);
        OpenAI::assertNotSent(\OpenAI\Resources\Audio::class);
    }

    public function test_voice_chat_keeps_same_correlation_id_across_audit_and_assistant_response(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $transcription = Mockery::mock(VoiceTranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->once()
            ->andReturn(['text' => 'Track correlation please', 'language' => 'ar', 'duration' => 1.0]);
        $this->app->instance(VoiceTranscriptionService::class, $transcription);

        OpenAI::fake([
            $this->fakeResponse('Correlation preserved'),
        ]);

        $response = $this->post('/api/ai/voice/chat', [
            'audio' => UploadedFile::fake()->create('clip.wav', 128, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $response->assertOk();

        $assistantConversation = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        $this->assertNotNull($assistantConversation);

        $correlationIds = AiAuditEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('action', [
                'voice_audio_received',
                'voice_transcript_generated',
                'voice_assistant_response_generated',
            ])
            ->pluck('correlation_id')
            ->filter()
            ->unique()
            ->values();

        $this->assertCount(1, $correlationIds);
        $this->assertSame($correlationIds->first(), $assistantConversation->metadata['correlation_id'] ?? null);
    }

    private function fakeResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'id' => 'resp_' . uniqid(),
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_' . uniqid(),
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $text,
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
