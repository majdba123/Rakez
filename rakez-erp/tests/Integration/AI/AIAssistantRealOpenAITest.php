<?php

namespace Tests\Integration\AI;

use App\Models\AIConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ReadsDotEnvForTest;
use Tests\Traits\TestsWithPermissions;
use Tests\Traits\TestsWithRealOpenAiConnection;

/**
 * Real OpenAI E2E tests — hit the live API using token from .env.
 *
 * Skipped unless .env is configured. To run:
 *   1. In .env set OPENAI_API_KEY=sk-... (your real token)
 *   2. In .env set AI_REAL_TESTS=true
 *   3. Run: php artisan test --group=ai-e2e-real
 *
 * Tests cover: /api/ai/ask, /api/ai/chat (JSON and SSE stream), session continuity.
 *
 * @group ai-e2e-real
 */
class AIAssistantRealOpenAITest extends TestCase
{
    use ReadsDotEnvForTest;
    use RefreshDatabase, TestsWithPermissions;
    use TestsWithRealOpenAiConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRealOpenAiFromDotEnv();
    }

    // ------------------------------------------------------------------
    // Test 1 — v1 /api/ai/ask
    // ------------------------------------------------------------------

    public function test_real_ask_endpoint_returns_ai_response(): void
    {
        $user = $this->authenticatedAiUser();

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'What is 2+2? Reply in one sentence.',
            'section' => 'general',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'message',
                'session_id',
                'conversation_id',
                'suggestions',
                'error_code',
            ],
        ]);

        $data = $response->json('data');

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($data['message']);
        $this->assertNotEmpty($data['session_id']);
        $this->assertIsInt($data['conversation_id']);
        $this->assertIsArray($data['suggestions']);
        $this->assertNull($data['error_code']);

        $sessionId = $data['session_id'];

        $userRow = AIConversation::where('session_id', $sessionId)
            ->where('role', 'user')
            ->first();
        $this->assertNotNull($userRow, 'User message row should exist in ai_conversations');

        $assistantRow = AIConversation::where('session_id', $sessionId)
            ->where('role', 'assistant')
            ->first();
        $this->assertNotNull($assistantRow, 'Assistant message row should exist in ai_conversations');
        $this->assertNotNull($assistantRow->model, 'model should be populated from real response');
        $this->assertNotNull($assistantRow->total_tokens, 'total_tokens should be populated');
        $this->assertNotNull($assistantRow->request_id, 'request_id should be populated');
    }

    // ------------------------------------------------------------------
    // Test 2 — v1 /api/ai/chat continuity
    // ------------------------------------------------------------------

    public function test_real_chat_continuity_in_same_session(): void
    {
        $user = $this->authenticatedAiUser();

        $response1 = $this->postJson('/api/ai/chat', [
            'message' => 'Hello, what is 3+3?',
            'section' => 'general',
        ]);

        $response1->assertOk();
        $this->assertNotEmpty($response1->json('data.message'));

        $sessionId = $response1->json('data.session_id');
        $this->assertNotEmpty($sessionId);

        $response2 = $this->postJson('/api/ai/chat', [
            'message' => 'And what is 4+4?',
            'session_id' => $sessionId,
            'section' => 'general',
        ]);

        $response2->assertOk();
        $this->assertNotEmpty($response2->json('data.message'));
        $this->assertEquals($sessionId, $response2->json('data.session_id'));

        $rowCount = AIConversation::where('session_id', $sessionId)
            ->where('is_summary', false)
            ->count();

        $this->assertGreaterThanOrEqual(4, $rowCount, 'Should have at least 4 rows (2 user + 2 assistant)');
    }

    // ------------------------------------------------------------------
    // Test 3 — /api/ai/chat without stream (full JSON)
    // ------------------------------------------------------------------

    public function test_real_chat_without_stream_returns_full_json(): void
    {
        $user = $this->authenticatedAiUser();

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Reply with exactly: OK',
            'section' => 'general',
            'stream' => false,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'message',
                'session_id',
                'conversation_id',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.message'));
        $this->assertNotEmpty($response->json('data.session_id'));
    }

    // ------------------------------------------------------------------
    // Test 4 — /api/ai/chat with stream=true (SSE)
    // ------------------------------------------------------------------

    public function test_real_chat_with_stream_returns_sse_chunks(): void
    {
        $user = $this->authenticatedAiUser();

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Say hello in one word.',
            'section' => 'general',
            'stream' => true,
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'text/event-stream',
            $response->headers->get('Content-Type'),
            'Streaming response must have Content-Type: text/event-stream'
        );

        $body = $response->streamedContent();
        $this->assertNotEmpty($body, 'Streamed body must not be empty');
        $this->assertStringContainsString('data: ', $body);
        $this->assertStringContainsString('data: [DONE]', $body);

        $chunks = [];
        $sessionId = null;
        $done = false;
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }
            $payload = substr($line, 6);
            if ($payload === '[DONE]') {
                $done = true;
                break;
            }
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                if (isset($decoded['chunk'])) {
                    $chunks[] = $decoded['chunk'];
                }
                if (isset($decoded['session_id'])) {
                    $sessionId = $decoded['session_id'];
                }
            }
        }

        $this->assertTrue($done, 'Stream must end with data: [DONE]');
        $this->assertNotEmpty($chunks, 'At least one chunk must be received');
        $this->assertNotEmpty($sessionId, 'Session ID must be present in stream');
    }

    // ------------------------------------------------------------------
    // Test 5 — tools orchestrator /api/ai/tools/chat strict JSON schema
    // ------------------------------------------------------------------

    public function test_real_v2_chat_returns_strict_json_schema(): void
    {
        $user = $this->authenticatedAiUser();

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'What can I do in this system?',
        ]);

        if ($response->status() === 404) {
            $this->markTestSkipped('Route /api/ai/tools/chat is not registered');
        }

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $data = $response->json('data');
        $this->assertIsArray($data);

        $this->assertArrayHasKey('answer_markdown', $data);
        $this->assertNotEmpty($data['answer_markdown']);

        $this->assertArrayHasKey('confidence', $data);
        $this->assertContains($data['confidence'], ['high', 'medium', 'low']);

        $this->assertArrayHasKey('sources', $data);
        $this->assertIsArray($data['sources']);

        $this->assertArrayHasKey('links', $data);
        $this->assertIsArray($data['links']);

        $this->assertArrayHasKey('suggested_actions', $data);
        $this->assertIsArray($data['suggested_actions']);

        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);

        $this->assertArrayHasKey('access_notes', $data);
        $this->assertIsBool($data['access_notes']['had_denied_request']);
        $this->assertIsString($data['access_notes']['reason']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authenticatedAiUser(): User
    {
        $user = $this->createUserWithPermissions(['use-ai-assistant']);
        Sanctum::actingAs($user);

        return $user;
    }

}
