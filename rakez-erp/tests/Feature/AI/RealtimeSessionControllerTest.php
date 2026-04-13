<?php

namespace Tests\Feature\AI;

use App\Models\AIConversation;
use App\Models\AiRealtimeSession;
use App\Models\AiRealtimeSessionEvent;
use App\Models\User;
use App\Services\AI\Realtime\RealtimeTransportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Mockery;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RealtimeSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('use-ai-assistant', 'web');

        config([
            'ai_realtime.enabled' => true,
            'ai_realtime.sessions.max_active_sessions_per_user' => 5,
            'ai_realtime.sessions.max_reconnects' => 2,
            'ai_realtime.rate_limits.session_create_per_minute' => 10,
            'ai_realtime.rate_limits.control_events_per_minute' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_realtime_session_create_requires_authenticated_ai_user(): void
    {
        $this->postJson('/api/ai/realtime/sessions', [])
            ->assertUnauthorized();
    }

    public function test_realtime_session_create_returns_control_plane_contract_and_audit_event(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/realtime/sessions', [
            'section' => 'sales',
            'requested_modalities' => ['audio', 'text'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AiRealtimeSession::STATUS_SESSION_CREATED)
            ->assertJsonPath('data.transport_mode', 'control_plane_only')
            ->assertJsonPath('data.metadata.provider_connected', false)
            ->assertJsonPath('data.section', 'sales');

        $publicId = $response->json('data.id');

        $this->assertDatabaseHas('ai_realtime_sessions', [
            'public_id' => $publicId,
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_SESSION_CREATED,
        ]);

        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'session_created',
            'state_after' => AiRealtimeSession::STATUS_SESSION_CREATED,
        ]);
    }

    public function test_realtime_session_is_session_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $owner->givePermissionTo('use-ai-assistant');

        $other = User::factory()->create();
        $other->givePermissionTo('use-ai-assistant');

        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'status' => AiRealtimeSession::STATUS_SESSION_CREATED,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'not_connected',
            'provider_model' => 'gpt-realtime',
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 2,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/ai/realtime/sessions/'.$session->public_id)
            ->assertForbidden();
    }

    public function test_realtime_session_supports_state_and_audit_lifecycle(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/start')->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/listening')->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/partial-transcript', [
            'text' => 'Hello from live audio',
            'is_final' => false,
        ])->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/thinking')->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/tool-start', [
            'tool_name' => 'tool_search_records',
        ])->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/tool-finish', [
            'tool_name' => 'tool_search_records',
        ])->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/speaking')->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/interrupt', [
            'reason' => 'user_barge_in',
        ])->assertOk();

        $rollback = $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/rollback', [
            'reason' => 'provider_transport_unavailable',
        ]);

        $rollback->assertOk()
            ->assertJsonPath('data.status', AiRealtimeSession::STATUS_ENDED)
            ->assertJsonPath('data.transport_status', 'rolled_back')
            ->assertJsonPath('data.rollback_target', 'voice_fallback');

        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'tool_call_started',
            'state_after' => AiRealtimeSession::STATUS_TOOL_RUNNING,
        ]);

        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'rollback_to_fallback_used',
            'state_after' => AiRealtimeSession::STATUS_ENDED,
        ]);
    }

    public function test_realtime_session_show_exposes_safe_usage_and_reconnect_telemetry(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_RECONNECTING,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'reconnecting',
            'provider_model' => 'gpt-realtime',
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 3,
            'reconnect_count' => 1,
            'estimated_input_tokens' => 120,
            'estimated_output_tokens' => 45,
            'estimated_total_tokens' => 165,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'metadata' => [
                'last_usage' => [
                    'input_tokens' => 120,
                    'output_tokens' => 45,
                    'total_tokens' => 165,
                ],
            ],
        ]);

        $this->getJson('/api/ai/realtime/sessions/'.$session->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', AiRealtimeSession::STATUS_RECONNECTING)
            ->assertJsonPath('data.transport_status', 'reconnecting')
            ->assertJsonPath('data.reconnect_count', 1)
            ->assertJsonPath('data.estimated_input_tokens', 120)
            ->assertJsonPath('data.estimated_output_tokens', 45)
            ->assertJsonPath('data.estimated_total_tokens', 165)
            ->assertJsonPath('data.metadata.last_usage.total_tokens', 165);
    }

    public function test_realtime_session_rejects_overlapping_tool_calls(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/start')->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/listening')->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/thinking')->assertOk();
        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/tool-start', [
            'tool_name' => 'tool_search_records',
        ])->assertOk();

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/tool-start', [
            'tool_name' => 'tool_kpi_sales',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'ai_realtime_tool_conflict');
    }

    public function test_realtime_session_reconnect_is_rejected_after_limit(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_SESSION_ACTIVE,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'not_connected',
            'provider_model' => 'gpt-realtime',
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 2,
            'reconnect_count' => 2,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/ai/realtime/sessions/'.$session->public_id.'/reconnect')
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'ai_realtime_reconnect_rejected');
    }

    public function test_realtime_session_client_event_is_queued_for_bridge_processing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/client-events', [
            'type' => 'response.cancel',
            'event' => [
                'reason' => 'user_interrupt',
            ],
        ])->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.type', 'response.cancel');

        $this->assertDatabaseHas('ai_realtime_session_events', [
            'direction' => 'client_to_provider',
            'event_type' => 'client_event_queued',
            'transport_event_type' => 'response.cancel',
        ]);
    }

    public function test_realtime_audio_append_event_requires_valid_base64_audio_payload(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/client-events', [
            'type' => 'input_audio_buffer.append',
            'event' => [
                'audio' => '%%%not-base64%%%',
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'ai_realtime_validation_failed');
    }

    public function test_realtime_audio_append_event_is_safely_queued_with_audio_byte_metadata(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');
        $audio = base64_encode(str_repeat("\x00", 32));

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/client-events', [
            'type' => 'input_audio_buffer.append',
            'event' => [
                'audio' => $audio,
            ],
        ])->assertStatus(202)
            ->assertJsonPath('data.type', 'input_audio_buffer.append');

        $event = AiRealtimeSessionEvent::query()
            ->where('transport_event_type', 'input_audio_buffer.append')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(32, $event->payload['meta']['audio_bytes'] ?? null);
    }

    public function test_realtime_response_create_rejects_unsupported_response_options(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/client-events', [
            'type' => 'response.create',
            'event' => [
                'response' => [
                    'modalities' => ['text'],
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'ai_realtime_validation_failed');
    }

    public function test_realtime_response_create_allows_instruction_only_payload(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/client-events', [
            'type' => 'response.create',
            'event' => [
                'response' => [
                    'instructions' => 'Reply briefly.',
                ],
            ],
        ])->assertStatus(202)
            ->assertJsonPath('data.type', 'response.create');
    }

    public function test_realtime_conversation_item_create_requires_text_message_blocks(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/ai/realtime/sessions', [])->json('data.id');

        $this->postJson('/api/ai/realtime/sessions/'.$sessionId.'/client-events', [
            'type' => 'conversation.item.create',
            'event' => [
                'item' => [
                    'type' => 'message',
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Hello from queued text'],
                    ],
                ],
            ],
        ])->assertStatus(202)
            ->assertJsonPath('data.type', 'conversation.item.create');
    }

    public function test_realtime_session_bridge_start_delegates_to_transport_manager(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_SESSION_ACTIVE,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'not_connected',
            'provider_model' => 'gpt-realtime',
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 2,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $manager = Mockery::mock(RealtimeTransportManager::class);
        $manager->shouldReceive('start')
            ->once()
            ->andReturn([
                'pid' => 12345,
                'status' => 'starting',
            ]);
        $this->app->instance(RealtimeTransportManager::class, $manager);

        $this->postJson('/api/ai/realtime/sessions/'.$session->public_id.'/bridge/start')
            ->assertStatus(202)
            ->assertJsonPath('data.pid', 12345)
            ->assertJsonPath('data.status', 'starting');
    }

    public function test_realtime_session_bridge_stop_marks_stop_requested(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_SESSION_ACTIVE,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'connected',
            'provider_model' => 'gpt-realtime',
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 2,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'metadata' => [
                'bridge' => [
                    'status' => 'running',
                    'stop_requested' => false,
                ],
            ],
        ]);

        $this->postJson('/api/ai/realtime/sessions/'.$session->public_id.'/bridge/stop', [
            'reason' => 'user_stop',
        ])->assertStatus(202)
            ->assertJsonPath('data.stop_requested', true);

        $fresh = $session->fresh();

        $this->assertTrue((bool) ($fresh->metadata['bridge']['stop_requested'] ?? false));
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'direction' => 'internal',
            'event_type' => 'transport_stop_requested',
            'transport_event_type' => 'transport.stop',
        ]);
    }

    public function test_realtime_session_create_respects_budget_guardrail(): void
    {
        config([
            'ai_assistant.budgets.per_user_daily_tokens' => 10,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        AIConversation::create([
            'user_id' => $user->id,
            'session_id' => (string) Str::uuid(),
            'role' => 'assistant',
            'message' => 'budgeted turn',
            'total_tokens' => 10,
        ]);

        $this->postJson('/api/ai/realtime/sessions', [])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'ai_budget_exceeded');
    }

    public function test_realtime_session_create_is_rate_limited_separately(): void
    {
        RateLimiter::for('ai-realtime-create', function (Request $request) {
            return Limit::perMinute(1)->by($request->user()?->id ?: $request->ip());
        });

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->postJson('/api/ai/realtime/sessions', [])->assertCreated();
        $this->postJson('/api/ai/realtime/sessions', [])->assertStatus(429);
    }
}
