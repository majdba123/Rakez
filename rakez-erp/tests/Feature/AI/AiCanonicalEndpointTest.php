<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Services\AI\Policy\RakizAiPolicyContextBuilder;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiCanonicalEndpointTest extends TestCase
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

    public function test_canonical_text_chat_returns_normalized_shape(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->mockPolicyAndOrchestrator();

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'Show sales summary',
            'conversation_id' => 'conv-123',
            'input_mode' => 'text',
            'locale' => 'en',
            'context' => [
                'module' => 'sales',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation_id', 'conv-123')
            ->assertJsonPath('data.answer', 'AI answer in markdown')
            ->assertJsonPath('data.answer_markdown', 'AI answer in markdown')
            ->assertJsonPath('data.meta.locale', 'en')
            ->assertJsonPath('data.meta.input_mode', 'text')
            ->assertJsonPath('data.meta.model', 'gpt-4.1-mini')
            ->assertJsonPath('data.meta.tokens', 88)
            ->assertJsonPath('data.meta.latency_ms', 321)
            ->assertJsonPath('data.meta.correlation_id', 'corr-1');
    }

    public function test_canonical_voice_metadata_is_processed_as_text(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->mockPolicyAndOrchestrator();

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'النص المفرغ من الصوت',
            'conversation_id' => 'voice-conv-1',
            'input_mode' => 'voice',
            'locale' => 'ar',
            'context' => [
                'module' => 'sales',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.input_mode', 'voice')
            ->assertJsonPath('data.meta.locale', 'ar')
            ->assertJsonPath('data.answer', 'AI answer in markdown');
    }

    public function test_canonical_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/ai/tools/chat', [
            'message' => 'hello',
        ])->assertUnauthorized();
    }

    public function test_canonical_returns_normalized_forbidden_when_missing_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'hello',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'forbidden')
            ->assertJsonPath('error.message', 'You do not have permission to use the AI assistant.');
    }

    public function test_canonical_validation_error_shape_is_normalized(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/tools/chat', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.message', 'Validation failed.');
    }

    public function test_pii_redaction_middleware_applies_on_canonical_route(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $capturedMessage = null;
        $policy = Mockery::mock(RakizAiPolicyContextBuilder::class);
        $policy->shouldReceive('buildDeterministicPolicySnapshot')
            ->once()
            ->withArgs(function ($u, $message, $section) use (&$capturedMessage) {
                $capturedMessage = $message;
                return true;
            })
            ->andReturn(['rules' => [], 'permissions' => [], 'tool_mode' => 'auto']);
        $policy->shouldReceive('earlyPolicyGateResponse')->andReturn(null);
        $policy->shouldReceive('applySnapshotNormalization')->andReturnUsing(fn ($data) => $data);
        $this->app->instance(RakizAiPolicyContextBuilder::class, $policy);

        $orchestrator = Mockery::mock(RakizAiOrchestrator::class);
        $orchestrator->shouldReceive('chat')->once()->andReturn([
            'answer_markdown' => 'AI answer in markdown',
            'confidence' => 'high',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => [],
            'access_notes' => ['had_denied_request' => false, 'reason' => ''],
            '_execution_meta' => [],
        ]);
        $this->app->instance(RakizAiOrchestrator::class, $orchestrator);

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'reach me at user@example.com',
        ]);

        $response->assertOk();
        $this->assertSame('reach me at [REDACTED_EMAIL]', $capturedMessage);
    }

    public function test_canonical_stream_returns_sse_compatible_response(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->mockPolicyAndOrchestrator();

        $response = $this->post('/api/ai/tools/stream', [
            'message' => 'Show sales summary',
            'conversation_id' => 'stream-conv-1',
            'input_mode' => 'text',
            'locale' => 'en',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('data: ', $content);
        $this->assertStringContainsString('"success":true', $content);
        $this->assertStringContainsString('"conversation_id":"stream-conv-1"', $content);
        $this->assertStringContainsString('data: [DONE]', $content);
    }

    public function test_legacy_routes_still_exist_and_are_not_removed(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->postJson('/api/ai/chat', [])->assertStatus(422);
        $this->postJson('/api/ai/ask', [])->assertStatus(422);
        $this->postJson('/api/ai/assistant/chat', [])->assertStatus(422);
        $this->postJson('/api/ai/v2/chat', [])->assertStatus(422);
    }

    private function mockPolicyAndOrchestrator(): void
    {
        $policy = Mockery::mock(RakizAiPolicyContextBuilder::class);
        $policy->shouldReceive('buildDeterministicPolicySnapshot')
            ->andReturn(['rules' => [], 'permissions' => [], 'tool_mode' => 'auto']);
        $policy->shouldReceive('earlyPolicyGateResponse')->andReturn(null);
        $policy->shouldReceive('applySnapshotNormalization')->andReturnUsing(fn ($data) => $data);
        $this->app->instance(RakizAiPolicyContextBuilder::class, $policy);

        $orchestrator = Mockery::mock(RakizAiOrchestrator::class);
        $orchestrator->shouldReceive('chat')->andReturn([
            'answer_markdown' => 'AI answer in markdown',
            'confidence' => 'high',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => [],
            'access_notes' => ['had_denied_request' => false, 'reason' => ''],
            '_execution_meta' => [
                'model' => 'gpt-4.1-mini',
                'total_tokens' => 88,
                'latency_ms' => 321,
                'correlation_id' => 'corr-1',
            ],
        ]);
        $this->app->instance(RakizAiOrchestrator::class, $orchestrator);
    }
}
