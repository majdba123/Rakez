<?php

namespace Tests\Integration\AI;

use App\Models\AIConversation;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;

class AIAssistantIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_ask_flow(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Here is a helpful answer to your question.'),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'How do I create a contract?',
            'section' => 'contracts',
            'context' => [
                'contract_id' => 123,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'message',
                'session_id',
                'conversation_id',
                'suggestions',
            ],
        ]);

        // Verify message was stored
        $conversation = AIConversation::where('session_id', $response->json('data.session_id'))->first();
        $this->assertNotNull($conversation);
        $this->assertEquals('user', $conversation->role);
    }

    public function test_full_chat_flow_with_history(): void
    {
        OpenAI::fake([
            $this->fakeResponse('First answer'),
            $this->fakeResponse('Second answer'),
            $this->fakeResponse('Third answer'),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = '11111111-1111-1111-1111-111111111111';

        // First message
        $response1 = $this->postJson('/api/ai/chat', [
            'message' => 'First question',
            'session_id' => $sessionId,
            'section' => 'general',
        ]);

        $response1->assertOk();

        // Second message (should include history)
        $response2 = $this->postJson('/api/ai/chat', [
            'message' => 'Second question',
            'session_id' => $sessionId,
            'section' => 'general',
        ]);

        $response2->assertOk();

        // Third message
        $response3 = $this->postJson('/api/ai/chat', [
            'message' => 'Third question',
            'session_id' => $sessionId,
            'section' => 'general',
        ]);

        $response3->assertOk();

        // Verify all messages stored
        $messages = AIConversation::where('session_id', $sessionId)
            ->where('is_summary', false)
            ->count();

        $this->assertGreaterThanOrEqual(6, $messages); // 3 user + 3 assistant
    }

    public function test_conversation_summary_creation(): void
    {
        config([
            'ai_assistant.chat.summary_every' => 2,
            'ai_assistant.chat.summary_window' => 2,
        ]);

        OpenAI::fake([
            $this->fakeResponse('Answer 1'),
            $this->fakeResponse('Answer 2'),
            $this->fakeResponse('Summary of conversation'),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = '11111111-1111-1111-1111-111111111111';

        $this->postJson('/api/ai/chat', [
            'message' => 'Message 1',
            'session_id' => $sessionId,
        ]);

        $this->postJson('/api/ai/chat', [
            'message' => 'Message 2',
            'session_id' => $sessionId,
        ]);

        // Verify summary was created
        $summary = AIConversation::where('session_id', $sessionId)
            ->where('is_summary', true)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals('assistant', $summary->role);
    }

    public function test_multiple_sessions_isolation(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Answer 1'),
            $this->fakeResponse('Answer 2'),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $session1 = '11111111-1111-1111-1111-111111111111';
        $session2 = '22222222-2222-2222-2222-222222222222';

        $this->postJson('/api/ai/chat', [
            'message' => 'Session 1 message',
            'session_id' => $session1,
        ]);

        $this->postJson('/api/ai/chat', [
            'message' => 'Session 2 message',
            'session_id' => $session2,
        ]);

        // Verify sessions are isolated
        $session1Messages = AIConversation::where('session_id', $session1)->count();
        $session2Messages = AIConversation::where('session_id', $session2)->count();

        $this->assertEquals(2, $session1Messages); // 1 user + 1 assistant
        $this->assertEquals(2, $session2Messages); // 1 user + 1 assistant
    }

    public function test_capability_resolution_flow(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view', 'units.view']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $sections = $response->json('data');

        // Should see contracts and units sections
        $hasContracts = false;
        $hasUnits = false;

        foreach ($sections as $section) {
            if (isset($section['label'])) {
                if ($section['label'] === 'Contracts') {
                    $hasContracts = true;
                }
                if ($section['label'] === 'Units') {
                    $hasUnits = true;
                }
            }
        }

        $this->assertTrue($hasContracts);
        $this->assertTrue($hasUnits);
    }

    public function test_context_building_flow(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $user = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Tell me about my contract',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id,
            ],
        ]);

        $response->assertOk();

        // Verify context was used (check metadata)
        $conversation = AIConversation::where('session_id', $response->json('data.session_id'))
            ->where('role', 'user')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertArrayHasKey('context', $conversation->metadata);
        $this->assertEquals($contract->id, $conversation->metadata['context']['contract_id']);
    }

    public function test_error_handling_flow(): void
    {
        OpenAI::fake([
            new \Exception('OpenAI API error'),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
        ]);

        $response->assertStatus(500);
    }

    public function test_delete_session_flow(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create session
        $askResponse = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
        ]);

        $sessionId = $askResponse->json('data.session_id');

        // Verify messages exist
        $beforeCount = AIConversation::where('session_id', $sessionId)->count();
        $this->assertGreaterThan(0, $beforeCount);

        // Delete session
        $deleteResponse = $this->deleteJson("/api/ai/conversations/{$sessionId}");

        $deleteResponse->assertOk();

        // Verify messages deleted
        $afterCount = AIConversation::where('session_id', $sessionId)->count();
        $this->assertEquals(0, $afterCount);
    }

    private function fakeResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'id' => 'resp_' . uniqid(),
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_' . uniqid(),
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $text,
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
