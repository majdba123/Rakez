<?php

namespace Tests\Feature\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;
use Tests\Traits\TestsWithAI;
use Tests\Traits\TestsWithPermissions;

class AIAssistantControllerTest extends TestCase
{
    use RefreshDatabase, TestsWithAI, TestsWithPermissions;

    public function test_ask_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
        ]);

        $response->assertUnauthorized();
    }

    public function test_ask_endpoint_validates_question_required(): void
    {
        $this->actingAsAuthorizedUser();

        $response = $this->postJson('/api/ai/ask', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['question']);
    }

    public function test_ask_endpoint_validates_question_max_length(): void
    {
        $this->actingAsAuthorizedUser();

        $this->mockAIResponse('Answer');

        $response = $this->postJson('/api/ai/ask', [
            'question' => str_repeat('a', 2001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['question']);
    }

    public function test_ask_endpoint_validates_section_in_list(): void
    {
        $this->actingAsAuthorizedUser();

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'invalid_section',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['section']);
    }

    public function test_ask_endpoint_validates_context_schema(): void
    {
        $this->actingAsAuthorizedUser();

        $this->mockAIResponse('Answer');

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => 'invalid', // Should be int
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['context.contract_id']);
    }

    public function test_ask_endpoint_returns_suggestions(): void
    {
        $this->actingAsAuthorizedUser();

        $this->mockAIResponse('Answer');

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'suggestions',
            ],
        ]);
    }

    public function test_chat_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Test message',
        ]);

        $response->assertUnauthorized();
    }

    public function test_chat_endpoint_validates_message_required(): void
    {
        $this->actingAsAuthorizedUser();

        $response = $this->postJson('/api/ai/chat', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_chat_endpoint_validates_session_id_uuid(): void
    {
        $this->actingAsAuthorizedUser();

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Test message',
            'session_id' => 'invalid-uuid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['session_id']);
    }

    public function test_chat_endpoint_creates_new_session(): void
    {
        $this->actingAsAuthorizedUser();

        $this->mockAIResponse('Answer');

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Test message',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'session_id',
            ],
        ]);
    }

    public function test_chat_endpoint_uses_existing_session(): void
    {
        $this->actingAsAuthorizedUser();

        $this->mockAIResponses(['First answer', 'Second answer']);

        $sessionId = '11111111-1111-1111-1111-111111111111';

        $firstResponse = $this->postJson('/api/ai/chat', [
            'message' => 'First message',
            'session_id' => $sessionId,
        ]);

        $secondResponse = $this->postJson('/api/ai/chat', [
            'message' => 'Second message',
            'session_id' => $sessionId,
        ]);

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $firstData = $firstResponse->json('data');
        $secondData = $secondResponse->json('data');

        $this->assertEquals($sessionId, $firstData['session_id']);
        $this->assertEquals($sessionId, $secondData['session_id']);
    }

    public function test_sections_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/sections');

        $response->assertUnauthorized();
    }

    public function test_sections_endpoint_filters_by_capabilities(): void
    {
        $this->actingAsAuthorizedUser(['type' => 'developer']);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
        ]);

        $sections = $response->json('data');
        $this->assertIsArray($sections);
    }

    public function test_conversations_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/conversations');

        $response->assertUnauthorized();
    }

    public function test_conversations_endpoint_paginates_results(): void
    {
        $this->actingAsAuthorizedUser();

        $this->mockAIResponses(['Answer', 'Answer']);

        $this->postJson('/api/ai/ask', ['question' => 'Question 1']);
        $this->postJson('/api/ai/ask', ['question' => 'Question 2']);

        $response = $this->getJson('/api/ai/conversations?per_page=1');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
            'meta' => [
                'pagination' => [
                    'current_page',
                    'total_pages',
                    'per_page',
                    'total',
                ],
            ],
        ]);
    }

    public function test_deleteSession_endpoint_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/ai/conversations/test-session-id');

        $response->assertUnauthorized();
    }

    public function test_deleteSession_endpoint_deletes_session(): void
    {
        $this->actingAsAuthorizedUser();

        $this->mockAIResponse('Answer');

        $askResponse = $this->postJson('/api/ai/ask', ['question' => 'Question']);
        $sessionId = $askResponse->json('data.session_id');

        $response = $this->deleteJson("/api/ai/conversations/{$sessionId}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'deleted' => true,
            ],
        ]);
    }

    private function actingAsAuthorizedUser(array $attributes = []): User
    {
        $user = $this->createUserWithPermissions(
            ['use-ai-assistant', 'contracts.view'],
            $attributes
        );
        Sanctum::actingAs($user);
        return $user;
    }
}
