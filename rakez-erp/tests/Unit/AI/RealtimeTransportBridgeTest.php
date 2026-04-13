<?php

namespace Tests\Unit\AI;

use App\Models\AiRealtimeSession;
use App\Models\User;
use App\Services\AI\Realtime\RealtimeTransportBridge;
use App\Services\AI\Realtime\RealtimeTransportClient;
use App\Services\AI\Realtime\RealtimeTransportEventStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealtimeTransportBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai_realtime.enabled' => true,
            'ai_realtime.openai.model' => 'gpt-realtime',
            'ai_realtime.openai.voice' => 'marin',
            'ai_realtime.openai.turn_detection' => 'semantic_vad',
            'ai_realtime.budgets.estimated_max_session_tokens' => 4000,
            'ai_assistant.budgets.per_user_daily_tokens' => 0,
        ]);
    }

    public function test_bridge_sends_initial_update_flushes_client_queue_and_records_provider_events(): void
    {
        $user = User::factory()->create();
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
            'metadata' => [
                'bridge' => [
                    'status' => 'starting',
                    'stop_requested' => false,
                ],
            ],
        ]);

        $eventStore = app(RealtimeTransportEventStore::class);
        $queued = $eventStore->enqueueClientEvent($session, 'response.cancel', [
            'type' => 'response.cancel',
            'reason' => 'user_interrupt',
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                [
                    'type' => 'session.created',
                    'session' => [
                        'id' => 'sess_test_123',
                    ],
                ],
                [
                    'type' => 'response.created',
                ],
                [
                    'type' => 'response.output_audio.delta',
                ],
                [
                    'type' => 'response.done',
                    'response' => [
                        'usage' => [
                            'input_tokens' => 10,
                            'output_tokens' => 4,
                            'total_tokens' => 14,
                        ],
                    ],
                ],
            ],
            afterEvents: function () use ($session): void {
                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge'] = array_merge($metadata['bridge'] ?? [], [
                    'stop_requested' => true,
                ]);
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $this->assertCount(2, $fakeClient->sentEvents);
        $this->assertSame('session.update', $fakeClient->sentEvents[0]['type']);
        $this->assertSame('response.cancel', $fakeClient->sentEvents[1]['type']);

        $this->assertNotNull($queued->fresh()->processed_at);

        $fresh = $session->fresh();

        $this->assertSame('sess_test_123', $fresh->provider_session_id);
        $this->assertSame('connected', $fresh->transport_status);
        $this->assertSame(AiRealtimeSession::STATUS_LISTENING, $fresh->status);
        $this->assertSame('stopped', $fresh->metadata['bridge']['status'] ?? null);
        $this->assertTrue((bool) ($fresh->metadata['provider_connected'] ?? false));
        $this->assertSame(14, $fresh->estimated_total_tokens);

        $this->assertDatabaseHas('ai_realtime_session_events', [
            'direction' => 'provider_to_client',
            'transport_event_type' => 'session.created',
        ]);

        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'usage_telemetry_updated',
        ]);
    }

    public function test_bridge_marks_interrupted_when_provider_detects_barge_in(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_ASSISTANT_SPEAKING,
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

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                [
                    'type' => 'input_audio_buffer.speech_started',
                ],
            ],
            afterEvents: function () use ($session): void {
                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge'] = array_merge($metadata['bridge'] ?? [], [
                    'stop_requested' => true,
                ]);
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $this->assertSame(AiRealtimeSession::STATUS_INTERRUPTED, $session->fresh()->status);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'interruption_occurred',
            'state_after' => AiRealtimeSession::STATUS_INTERRUPTED,
        ]);
    }

    public function test_bridge_flushes_audio_append_commit_and_response_create_in_order(): void
    {
        $user = User::factory()->create();
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
            'metadata' => [
                'bridge' => [
                    'status' => 'starting',
                    'stop_requested' => false,
                ],
            ],
        ]);

        $eventStore = app(RealtimeTransportEventStore::class);
        $eventStore->enqueueClientEvent($session, 'input_audio_buffer.append', [
            'type' => 'input_audio_buffer.append',
            'audio' => base64_encode(str_repeat("\x00", 16)),
            'meta' => ['audio_bytes' => 16],
        ]);
        $eventStore->enqueueClientEvent($session, 'input_audio_buffer.commit', [
            'type' => 'input_audio_buffer.commit',
        ]);
        $eventStore->enqueueClientEvent($session, 'response.create', [
            'type' => 'response.create',
            'response' => ['instructions' => 'Reply briefly.'],
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                ['type' => 'session.created', 'session' => ['id' => 'sess_audio_1']],
                ['type' => 'response.created'],
                ['type' => 'response.output_audio.delta'],
                ['type' => 'response.done'],
            ],
            afterEvents: function () use ($session): void {
                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge'] = array_merge($metadata['bridge'] ?? [], [
                    'stop_requested' => true,
                ]);
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $this->assertCount(4, $fakeClient->sentEvents);
        $this->assertSame('session.update', $fakeClient->sentEvents[0]['type']);
        $this->assertSame('input_audio_buffer.append', $fakeClient->sentEvents[1]['type']);
        $this->assertSame('input_audio_buffer.commit', $fakeClient->sentEvents[2]['type']);
        $this->assertSame('response.create', $fakeClient->sentEvents[3]['type']);
        $this->assertSame(AiRealtimeSession::STATUS_LISTENING, $session->fresh()->status);
    }

    public function test_bridge_recovers_to_listening_after_interruption_and_response_done(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_ASSISTANT_SPEAKING,
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

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                ['type' => 'input_audio_buffer.speech_started'],
                ['type' => 'response.done'],
            ],
            afterEvents: function () use ($session): void {
                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge'] = array_merge($metadata['bridge'] ?? [], [
                    'stop_requested' => true,
                ]);
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $this->assertSame(AiRealtimeSession::STATUS_LISTENING, $session->fresh()->status);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'transport_event_type' => 'input_audio_buffer.speech_started',
            'direction' => 'provider_to_client',
        ]);
    }

    public function test_bridge_marks_unexpected_disconnect_as_reconnecting(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_LISTENING,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'connected',
            'provider_model' => 'gpt-realtime',
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 2,
            'reconnect_count' => 0,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'metadata' => [
                'bridge' => [
                    'status' => 'running',
                    'stop_requested' => false,
                ],
            ],
        ]);

        $this->app->instance(RealtimeTransportClient::class, new FakeRealtimeTransportClient([]));

        app(RealtimeTransportBridge::class)->run($session, 1);

        $fresh = $session->fresh();
        $this->assertSame(AiRealtimeSession::STATUS_RECONNECTING, $fresh->status);
        $this->assertSame(1, $fresh->reconnect_count);
        $this->assertSame('reconnecting', $fresh->transport_status);
        $this->assertSame('reconnecting', $fresh->metadata['bridge']['status'] ?? null);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'transport_disconnected',
            'state_after' => AiRealtimeSession::STATUS_RECONNECTING,
        ]);
    }

    public function test_bridge_restores_session_after_reconnect_and_tracks_live_tool_events(): void
    {
        $user = User::factory()->create();
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
            'max_reconnects' => 2,
            'reconnect_count' => 1,
            'started_at' => now()->subMinute(),
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'metadata' => [
                'bridge' => [
                    'status' => 'reconnecting',
                    'stop_requested' => false,
                ],
            ],
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                ['type' => 'session.updated', 'session' => ['id' => 'sess_reconnected']],
                ['type' => 'response.created'],
                ['type' => 'response.output_item.added', 'item' => ['type' => 'function_call', 'name' => 'tool_sales_advisor', 'call_id' => 'call_1', 'arguments' => '{"question":"follow up"}']],
                ['type' => 'response.output_item.done', 'item' => ['type' => 'function_call', 'name' => 'tool_sales_advisor', 'call_id' => 'call_1', 'arguments' => '{"question":"follow up"}']],
                ['type' => 'response.done'],
            ],
            afterEvents: function () use ($session): void {
                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge'] = array_merge($metadata['bridge'] ?? [], [
                    'stop_requested' => true,
                ]);
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $fresh = $session->fresh();
        $this->assertSame(AiRealtimeSession::STATUS_LISTENING, $fresh->status);
        $this->assertSame('connected', $fresh->transport_status);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'reconnect_restored',
            'state_after' => AiRealtimeSession::STATUS_LISTENING,
        ]);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'tool_call_started',
            'state_after' => AiRealtimeSession::STATUS_TOOL_RUNNING,
        ]);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'tool_call_finished',
            'state_after' => AiRealtimeSession::STATUS_ASSISTANT_THINKING,
        ]);
        $this->assertSame('conversation.item.create', $fakeClient->sentEvents[1]['type']);
        $this->assertSame('function_call_output', $fakeClient->sentEvents[1]['item']['type']);
        $this->assertSame('response.create', $fakeClient->sentEvents[2]['type']);
    }

    public function test_duplicate_provider_event_id_is_ignored_without_double_counting_usage(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_LISTENING,
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
            'metadata' => ['bridge' => ['status' => 'running', 'stop_requested' => false]],
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                ['type' => 'response.done', 'event_id' => 'evt_dup_1', 'response' => ['usage' => ['input_tokens' => 5, 'output_tokens' => 2, 'total_tokens' => 7]]],
                ['type' => 'response.done', 'event_id' => 'evt_dup_1', 'response' => ['usage' => ['input_tokens' => 5, 'output_tokens' => 2, 'total_tokens' => 7]]],
            ],
            afterEvents: function () use ($session): void {
                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge']['stop_requested'] = true;
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $this->assertSame(7, $session->fresh()->estimated_total_tokens);
        $this->assertSame(1, $session->events()->where('transport_event_id', 'evt_dup_1')->count());
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'provider_event_duplicate_ignored',
        ]);
    }

    public function test_malformed_provider_event_is_audited_and_ignored(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_LISTENING,
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
            'metadata' => ['bridge' => ['status' => 'running', 'stop_requested' => false]],
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [['event_id' => 'bad_1']],
            afterEvents: function () use ($session): void {
                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge']['stop_requested'] = true;
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'transport_provider_event_malformed',
        ]);
    }

    public function test_stop_request_during_tool_run_suppresses_continuation_response(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_ASSISTANT_THINKING,
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
            'metadata' => ['bridge' => ['status' => 'running', 'stop_requested' => false]],
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                ['type' => 'response.output_item.added', 'item' => ['type' => 'function_call', 'name' => 'tool_sales_advisor', 'call_id' => 'call_stop', 'arguments' => '{"question":"follow up"}']],
                ['type' => 'response.output_item.done', 'item' => ['type' => 'function_call', 'name' => 'tool_sales_advisor', 'call_id' => 'call_stop', 'arguments' => '{"question":"follow up"}']],
            ],
            beforeEvent: function (int $index) use ($session): void {
                if ($index !== 1) {
                    return;
                }

                $fresh = $session->fresh();
                $metadata = $fresh->metadata ?? [];
                $metadata['bridge']['stop_requested'] = true;
                $fresh->update(['metadata' => $metadata]);
            }
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $sentTypes = array_map(fn (array $event) => $event['type'], $fakeClient->sentEvents);
        $this->assertContains('conversation.item.create', $sentTypes);
        $this->assertNotContains('response.create', array_slice($sentTypes, 1));
    }

    public function test_budget_exhaustion_mid_session_terminates_realtime_session(): void
    {
        config([
            'ai_realtime.budgets.estimated_max_session_tokens' => 10,
        ]);

        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_LISTENING,
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
            'metadata' => ['bridge' => ['status' => 'running', 'stop_requested' => false]],
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [
                ['type' => 'response.done', 'response' => ['usage' => ['input_tokens' => 7, 'output_tokens' => 5, 'total_tokens' => 12]]],
            ]
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $fresh = $session->fresh();
        $this->assertSame(AiRealtimeSession::STATUS_ENDED, $fresh->status);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'budget_exhausted',
            'error_code' => 'ai_budget_exceeded',
        ]);
    }

    public function test_provider_timeout_failure_marks_session_reconnecting(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_LISTENING,
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
            'metadata' => ['bridge' => ['status' => 'running', 'stop_requested' => false]],
        ]);

        $fakeClient = new FakeRealtimeTransportClient(
            providerEvents: [],
            exceptionToThrow: new \RuntimeException('timeout')
        );

        $this->app->instance(RealtimeTransportClient::class, $fakeClient);

        app(RealtimeTransportBridge::class)->run($session, 1);

        $this->assertSame(AiRealtimeSession::STATUS_RECONNECTING, $session->fresh()->status);
        $this->assertDatabaseHas('ai_realtime_session_events', [
            'event_type' => 'transport_bridge_failed',
            'error_code' => 'ai_realtime_transport_failed',
        ]);
    }

    public function test_max_reconnect_exhaustion_rolls_back_session(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_LISTENING,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'connected',
            'provider_model' => 'gpt-realtime',
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 1,
            'reconnect_count' => 1,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'metadata' => ['bridge' => ['status' => 'running', 'stop_requested' => false]],
        ]);

        $this->app->instance(RealtimeTransportClient::class, new FakeRealtimeTransportClient([]));

        app(RealtimeTransportBridge::class)->run($session, 1);

        $fresh = $session->fresh();
        $this->assertSame(AiRealtimeSession::STATUS_ENDED, $fresh->status);
        $this->assertSame('rolled_back', $fresh->transport_status);
    }
}

class FakeRealtimeTransportClient implements RealtimeTransportClient
{
    /**
     * @param  array<int, array<string, mixed>>  $providerEvents
     */
    public function __construct(
        private array $providerEvents = [],
        private $afterEvents = null,
        private $beforeEvent = null,
        private ?\Throwable $exceptionToThrow = null,
    ) {}

    /** @var array<int, array<string, mixed>> */
    public array $sentEvents = [];

    public function run(
        callable $onEvent,
        ?callable $onOpen = null,
        ?callable $onTick = null,
        ?callable $shouldStop = null,
        int $timeoutSeconds = 30,
    ): void {
        if ($onOpen !== null) {
            $onOpen();
        }

        if ($onTick !== null) {
            $onTick();
        }

        foreach ($this->providerEvents as $index => $event) {
            if (is_callable($this->beforeEvent)) {
                ($this->beforeEvent)($index, $event);
            }
            $onEvent($event);
        }

        if ($this->exceptionToThrow !== null) {
            throw $this->exceptionToThrow;
        }

        if (is_callable($this->afterEvents)) {
            ($this->afterEvents)();
        }

        if ($shouldStop !== null) {
            $shouldStop();
        }
    }

    public function send(array $event): void
    {
        $this->sentEvents[] = $event;
    }

    public function stop(): void
    {
    }
}
