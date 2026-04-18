<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Services\AI\AIAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiHardeningFeatureTest extends TestCase
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

    public function test_stream_denied_user_receives_403_not_200(): void
    {
        $user = User::factory()->create();
        // Do NOT give use-ai-assistant permission
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'test',
            'stream' => true,
        ]);

        // Must be 403, NOT 200 with SSE error — auth check happens before StreamedResponse
        $this->assertSame(403, $response->getStatusCode());
        $response->assertJson([
            'success' => false,
            'message' => 'You do not have permission to use the AI assistant.',
        ]);
    }

    public function test_conversations_per_page_is_capped_at_100(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        // The controller caps per_page at 100 — verified via response metadata
        $response = $this->getJson('/api/ai/conversations?per_page=500');
        $response->assertOk();
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page', 20));
    }

    public function test_pii_in_message_is_redacted_at_route_level(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $mock = Mockery::mock(AIAssistantService::class);
        $mock->shouldReceive('chat')
            ->once()
            ->withArgs(function (string $message) {
                // The middleware should have redacted the phone number
                return str_contains($message, '[REDACTED_PHONE]')
                    && ! str_contains($message, '0512345678');
            })
            ->andReturn([
                'message' => 'ok',
                'session_id' => '11111111-1111-1111-1111-111111111111',
                'conversation_id' => null,
                'meta' => [],
            ]);
        $mock->shouldReceive('suggestions')->andReturn([]);

        $this->app->instance(AIAssistantService::class, $mock);

        $this->postJson('/api/ai/chat', [
            'message' => 'جوال العميل 0512345678',
        ])->assertOk();
    }

    public function test_chat_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/chat', [
            'message' => 'test',
        ]);

        $response->assertUnauthorized();
    }

    public function test_conversations_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/conversations');

        $response->assertUnauthorized();
    }

    public function test_non_stream_denied_user_gets_403(): void
    {
        $user = User::factory()->create();
        // No use-ai-assistant permission
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'test',
        ]);

        // Non-stream path also checks permission via ai.assistant middleware
        $this->assertSame(403, $response->getStatusCode());
    }
}
