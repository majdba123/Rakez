<?php

namespace Tests\Unit\AI;

use App\Models\AiRealtimeSession;
use App\Models\User;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeBridgeLeaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealtimeBridgeLeaseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai_realtime.transport.bridge_stale_after_seconds' => 30,
        ]);
    }

    public function test_non_stale_bridge_owner_conflict_is_rejected(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_SESSION_ACTIVE,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'connected',
            'provider_model' => 'gpt-realtime',
            'bridge_owner_token' => 'owner-a',
            'bridge_owner_pid' => 111,
            'bridge_started_at' => now()->subSeconds(5),
            'bridge_heartbeat_at' => now(),
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 2,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->expectException(AiAssistantException::class);
        $this->expectExceptionMessage('Realtime bridge ownership conflict for this session.');

        app(RealtimeBridgeLeaseService::class)->acquire($session, 'owner-b', 222);
    }

    public function test_stale_bridge_owner_can_be_recovered_by_new_bridge(): void
    {
        $user = User::factory()->create();
        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_SESSION_ACTIVE,
            'transport' => 'websocket',
            'transport_mode' => 'control_plane_only',
            'transport_status' => 'connected',
            'provider_model' => 'gpt-realtime',
            'bridge_owner_token' => 'stale-owner',
            'bridge_owner_pid' => 111,
            'bridge_started_at' => now()->subMinutes(2),
            'bridge_heartbeat_at' => now()->subMinutes(2),
            'rollback_target' => 'voice_fallback',
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => 900,
            'max_reconnects' => 2,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $fresh = app(RealtimeBridgeLeaseService::class)->acquire($session, 'new-owner', 333);

        $this->assertSame('new-owner', $fresh->bridge_owner_token);
        $this->assertSame(333, $fresh->bridge_owner_pid);
        $this->assertNotNull($fresh->bridge_heartbeat_at);
    }
}
