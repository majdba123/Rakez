<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Services\AI\AiTextClientManager;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\TestsWithAI;

/**
 * Mock tests for AI chat streaming (SSE) and non-streaming (full JSON).
 * No real OpenAI token used — OpenAI is faked or client is mocked.
 */
class AIAssistantStreamingTest extends TestCase
{
    use RefreshDatabase, TestsWithAI;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disableAIBudget();
        config(['ai_assistant.enabled' => true]);
        Permission::findOrCreate('use-ai-assistant', 'web');
    }

    public function test_chat_without_stream_returns_full_json(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $this->mockAIResponse('Full reply in one shot.');

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Hello',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.message', 'Full reply in one shot.');
        $response->assertJsonStructure([
            'data' => [
                'session_id',
                'conversation_id',
                'message',
            ],
        ]);
    }

    public function test_chat_with_stream_false_returns_full_json(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $this->mockAIResponse('Another full reply.');

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Hi',
            'stream' => false,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertJsonPath('data.message', 'Another full reply.');
    }

    public function test_chat_with_stream_true_returns_sse_content_type(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $this->mockStreamingClient();

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Stream me',
            'stream' => true,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_chat_stream_response_contains_sse_chunks_and_done(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $this->mockStreamingClient();

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Stream me',
            'stream' => true,
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertNotEmpty($body);
        $this->assertStringContainsString('data: ', $body);
        $this->assertStringContainsString('"chunk"', $body);
        $this->assertStringContainsString('data: [DONE]', $body);
    }

    public function test_chat_stream_validates_stream_as_boolean(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Hi',
            'stream' => 'true',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['stream']);
    }

    public function test_chat_stream_fallback_to_full_json_when_streaming_throws(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $this->mockStreamingClientThatThrowsThenSucceeds();

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Fallback me',
            'stream' => true,
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertNotEmpty($body);
        $this->assertStringContainsString('data: ', $body);
        $this->assertStringContainsString('data: [DONE]', $body);
        $this->assertStringContainsString('Fallback full reply', $body);
    }

    public function test_chat_stream_parses_to_chunks_and_final_metadata(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $this->mockStreamingClient();

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Parse me',
            'stream' => true,
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $lines = array_filter(explode("\n", $body));
        $dataLines = array_values(array_filter($lines, fn ($l) => str_starts_with(trim($l), 'data: ')));
        $this->assertGreaterThanOrEqual(2, count($dataLines));

        $chunks = [];
        $done = false;
        foreach ($dataLines as $line) {
            $payload = trim(substr($line, 6));
            if ($payload === '[DONE]') {
                $done = true;
                break;
            }
            $decoded = json_decode($payload, true);
            if (is_array($decoded) && isset($decoded['chunk'])) {
                $chunks[] = $decoded['chunk'];
            }
            if (is_array($decoded) && isset($decoded['done']) && $decoded['done'] === true) {
                $this->assertArrayHasKey('session_id', $decoded);
            }
        }
        $this->assertTrue($done, 'Response must end with data: [DONE]');
        $this->assertNotEmpty($chunks, 'At least one chunk should be present');
    }

    public function test_chat_with_stream_true_uses_orchestrator_for_tool_sections(): void
    {
        $user = $this->assistantUser();
        Sanctum::actingAs($user);

        $manager = \Mockery::mock(AiTextClientManager::class);
        $manager->shouldReceive('createStreamedResponse')->never();
        $manager->shouldReceive('createResponse')->never();
        $this->app->instance(AiTextClientManager::class, $manager);

        $orchestrator = \Mockery::mock(RakizAiOrchestrator::class);
        $orchestrator->shouldReceive('chat')
            ->once()
            ->andReturn([
                'answer_markdown' => 'Tool mode streamed answer',
                'confidence' => 'high',
                'sources' => [['type' => 'tool', 'ref' => 'tool:test']],
                'links' => [],
                'suggested_actions' => [],
                'follow_up_questions' => ['next'],
                'access_notes' => ['had_denied_request' => false, 'reason' => ''],
                '_execution_meta' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                    'request_id' => 'req_stream_tools',
                    'correlation_id' => 'corr_stream_tools',
                    'latency_ms' => 120,
                    'model' => 'gpt-4.1-mini',
                ],
            ]);
        $this->app->instance(RakizAiOrchestrator::class, $orchestrator);

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Give me campaign analytics',
            'stream' => true,
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('Tool mode streamed answer', $body);
        $this->assertStringContainsString('"tool_mode":true', $body);
        $this->assertStringContainsString('data: [DONE]', $body);
    }

    private function mockStreamingClient(): void
    {
        $generator = function (): \Generator {
            yield 'Hello ';
            yield 'world';
        };

        $manager = \Mockery::mock(AiTextClientManager::class)->makePartial();
        $manager->shouldReceive('createStreamedResponse')
            ->andReturnUsing($generator);
        $manager->shouldReceive('createResponse')->never();

        $this->app->instance(AiTextClientManager::class, $manager);
    }

    private function mockStreamingClientThatThrowsThenSucceeds(): void
    {
        $manager = \Mockery::mock(AiTextClientManager::class)->makePartial();
        $manager->shouldReceive('createStreamedResponse')
            ->andThrow(new \RuntimeException('Stream not supported'));
        $manager->shouldReceive('createResponse')
            ->andReturn(new \App\Services\AI\Data\AiTextResponse(
                provider: 'openai',
                text: 'Fallback full reply',
                model: 'gpt-4.1-mini',
                responseId: 'resp_fallback',
                promptTokens: 10,
                completionTokens: 5,
                totalTokens: 15,
                latencyMs: 100,
                requestId: 'req_fallback',
                correlationId: 'corr_fallback',
            ));

        $this->app->instance(AiTextClientManager::class, $manager);
    }

    private function assistantUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->givePermissionTo('use-ai-assistant');

        return $user->fresh();
    }
}
