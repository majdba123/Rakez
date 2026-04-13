<?php

namespace Tests\Feature\AI;

use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\AI\AIAssistantService;
use App\Services\AI\AssistantLLMService;
use App\Services\AI\Policy\RakizAiPolicyContextBuilder;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiPolicyConsistencyHardProofTest extends TestCase
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

    public function test_permission_refusal_contract_is_consistent_across_ai_entrypoints(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $message = 'You do not have permission to use the AI assistant.';

        $this->postJson('/api/ai/ask', ['question' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('message', $message);

        $this->postJson('/api/ai/chat', ['message' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('message', $message);

        $this->postJson('/api/ai/tools/chat', ['message' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('message', $message);

        $this->postJson('/api/ai/assistant/chat', ['message' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('message', $message);

        $this->post('/api/ai/tools/stream', ['message' => 'x'], [
            'Accept' => 'text/event-stream',
        ])->assertStatus(403)
            ->assertJsonPath('message', $message);
    }

    public function test_chat_route_redacts_message_before_hitting_service(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $mock = Mockery::mock(AIAssistantService::class);
        $mock->shouldReceive('chat')
            ->once()
            ->withArgs(function (string $message, User $resolvedUser, $sessionId, $section, array $context) use ($user): bool {
                return $resolvedUser->is($user)
                    && $message === 'اتصل على [REDACTED_PHONE]'
                    && $sessionId === null
                    && $section === null
                    && $context === [];
            })
            ->andReturn([
                'message' => 'ok',
                'session_id' => '11111111-1111-1111-1111-111111111111',
                'conversation_id' => 1,
                'meta' => [],
            ]);
        $mock->shouldReceive('suggestions')->once()->with(null)->andReturn([]);

        $this->app->instance(AIAssistantService::class, $mock);

        $this->postJson('/api/ai/chat', [
            'message' => 'اتصل على 0512345678',
        ])->assertOk();
    }

    public function test_tools_chat_redacts_message_before_orchestrator_and_hides_execution_meta(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $policy = Mockery::mock(RakizAiPolicyContextBuilder::class);
        $policy->shouldReceive('buildDeterministicPolicySnapshot')->once()->andReturn(['snapshot' => true]);
        $policy->shouldReceive('earlyPolicyGateResponse')->once()->andReturn(null);
        $policy->shouldReceive('applySnapshotNormalization')
            ->once()
            ->andReturnUsing(fn (array $result) => $result);
        $this->app->instance(RakizAiPolicyContextBuilder::class, $policy);

        $orchestrator = Mockery::mock(RakizAiOrchestrator::class);
        $orchestrator->shouldReceive('chat')
            ->once()
            ->withArgs(function (User $resolvedUser, string $message, $sessionId, array $context) use ($user): bool {
                return $resolvedUser->is($user)
                    && $message === 'العميل بريده [REDACTED_EMAIL]'
                    && ($context['policy_snapshot']['snapshot'] ?? false) === true
                    && ($context['section'] ?? null) === 'general';
            })
            ->andReturn([
                'answer_markdown' => 'safe tool answer',
                'confidence' => 'high',
                'sources' => [],
                'links' => [],
                'suggested_actions' => [],
                'follow_up_questions' => [],
                'access_notes' => ['had_denied_request' => false, 'reason' => ''],
                '_execution_meta' => ['secret' => 'must-not-leak'],
            ]);
        $this->app->instance(RakizAiOrchestrator::class, $orchestrator);

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'العميل بريده test@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.answer_markdown', 'safe tool answer');

        $this->assertArrayNotHasKey('_execution_meta', $response->json('data'));
        $this->assertStringNotContainsString('must-not-leak', $response->getContent());
        $this->assertStringNotContainsString('test@example.com', $response->getContent());
    }

    public function test_tools_stream_redacts_message_before_orchestrator_and_hides_execution_meta(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $policy = Mockery::mock(RakizAiPolicyContextBuilder::class);
        $policy->shouldReceive('buildDeterministicPolicySnapshot')->once()->andReturn(['snapshot' => true]);
        $policy->shouldReceive('earlyPolicyGateResponse')->once()->andReturn(null);
        $policy->shouldReceive('applySnapshotNormalization')
            ->once()
            ->andReturnUsing(fn (array $result) => $result);
        $this->app->instance(RakizAiPolicyContextBuilder::class, $policy);

        $orchestrator = Mockery::mock(RakizAiOrchestrator::class);
        $orchestrator->shouldReceive('chat')
            ->once()
            ->withArgs(function (User $resolvedUser, string $message): bool {
                return $resolvedUser instanceof User
                    && $message === 'هوية العميل [REDACTED_NATIONAL_ID]';
            })
            ->andReturn([
                'answer_markdown' => 'stream-safe tool answer',
                'confidence' => 'high',
                'sources' => [],
                'links' => [],
                'suggested_actions' => [],
                'follow_up_questions' => [],
                'access_notes' => ['had_denied_request' => false, 'reason' => ''],
                '_execution_meta' => ['secret' => 'must-not-leak'],
            ]);
        $this->app->instance(RakizAiOrchestrator::class, $orchestrator);

        $response = $this->post('/api/ai/tools/stream', [
            'message' => 'هوية العميل 1098765432',
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('stream-safe tool answer', $body);
        $this->assertStringNotContainsString('_execution_meta', $body);
        $this->assertStringNotContainsString('must-not-leak', $body);
        $this->assertStringNotContainsString('1098765432', $body);
    }

    public function test_assistant_chat_redacts_user_message_and_persists_only_redacted_copy(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $llm = Mockery::mock(AssistantLLMService::class);
        $llm->shouldReceive('generateAnswer')
            ->once()
            ->withArgs(function (string $systemPrompt, array $knowledgeSnippets, string $userMessage): bool {
                return $userMessage === 'إيميل العميل [REDACTED_EMAIL]';
            })
            ->andReturn([
                'answer' => 'safe answer',
                'tokens' => 12,
                'latency_ms' => 30,
            ]);
        $this->app->instance(AssistantLLMService::class, $llm);

        $response = $this->postJson('/api/ai/assistant/chat', [
            'message' => 'إيميل العميل test@example.com',
            'language' => 'ar',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reply', 'safe answer');

        $userMessage = AssistantMessage::query()
            ->where('role', 'user')
            ->latest('id')
            ->value('content');

        $this->assertSame('إيميل العميل [REDACTED_EMAIL]', $userMessage);
        $this->assertStringNotContainsString('test@example.com', (string) $userMessage);
        $this->assertStringNotContainsString('test@example.com', $response->getContent());
    }

    private function assistantUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        return $user->fresh();
    }
}
