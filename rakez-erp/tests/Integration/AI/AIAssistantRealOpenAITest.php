<?php

namespace Tests\Integration\AI;

use App\Models\AIConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

/**
 * Real OpenAI E2E tests — hit the live API.
 *
 * Skipped by default. To run locally:
 *   1. Set OPENAI_API_KEY=sk-... in .env
 *   2. Set AI_REAL_TESTS=true  in .env
 *   3. php artisan test --filter=AIAssistantRealOpenAI
 */
class AIAssistantRealOpenAITest extends TestCase
{
    use RefreshDatabase, TestsWithPermissions;

    private string $realApiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $envKey = $this->readRealKeyFromDotEnv();

        if (! $envKey || $envKey === 'test-fake-key-not-used') {
            $this->markTestSkipped('Real OPENAI_API_KEY not configured in .env');
        }

        if (! $this->isRealTestsEnabled()) {
            $this->markTestSkipped('AI_REAL_TESTS is not enabled — set AI_REAL_TESTS=true in .env to run');
        }

        $this->realApiKey = $envKey;

        Config::set('openai.api_key', $this->realApiKey);
        Config::set('ai_assistant.enabled', true);
        Config::set('ai_assistant.budgets.per_user_daily_tokens', 0);
        Config::set('ai_assistant.openai.max_output_tokens', 200);
        Config::set('ai_assistant.v2.openai.max_output_tokens', 400);
        Config::set('ai_assistant.retries.max_attempts', 2);
        Config::set('ai_assistant.retries.base_delay_ms', 200);

        app()->forgetInstance('openai');
        app()->forgetInstance(\OpenAI\Client::class);

        try {
            $client = \OpenAI::client($this->realApiKey);
            $client->models()->list();
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            if (str_contains($e->getMessage(), 'Country') || str_contains($e->getMessage(), 'region') || str_contains($e->getMessage(), 'territory')) {
                $this->markTestSkipped('OpenAI API is not available in this region: ' . $e->getMessage());
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->markTestSkipped('OpenAI API connectivity issue: ' . $e->getMessage());
        }
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
    // Test 3 — v2 /api/ai/v2/chat strict JSON schema
    // ------------------------------------------------------------------

    public function test_real_v2_chat_returns_strict_json_schema(): void
    {
        $user = $this->authenticatedAiUser();

        $response = $this->postJson('/api/ai/v2/chat', [
            'message' => 'What can I do in this system?',
        ]);

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

    /**
     * Read the real OPENAI_API_KEY directly from .env, bypassing phpunit.xml override.
     */
    private function readRealKeyFromDotEnv(): ?string
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'OPENAI_API_KEY=')) {
                $value = substr($line, strlen('OPENAI_API_KEY='));
                $value = trim($value, '"\'');

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * Check AI_REAL_TESTS flag from .env (phpunit.xml may not have it).
     */
    private function isRealTestsEnabled(): bool
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return false;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'AI_REAL_TESTS=')) {
                $value = strtolower(trim(substr($line, strlen('AI_REAL_TESTS=')), '"\''));

                return in_array($value, ['true', '1', 'yes'], true);
            }
        }

        return false;
    }
}
