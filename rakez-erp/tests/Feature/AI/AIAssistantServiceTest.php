<?php

namespace Tests\Feature\AI;

use App\Models\AIConversation;
use App\Models\User;
use App\Services\AI\AIAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;

class AIAssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private AIAssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AIAssistantService::class);
    }

    public function test_ask_creates_new_session(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Test answer'),
        ]);

        $user = User::factory()->create();

        $result = $this->service->ask('Test question', $user);

        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['session_id']);
    }

    public function test_ask_stores_user_message(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Test answer'),
        ]);

        $user = User::factory()->create();
        $result = $this->service->ask('Test question', $user);

        $message = AIConversation::where('session_id', $result['session_id'])
            ->where('role', 'user')
            ->first();

        $this->assertNotNull($message);
        $this->assertEquals('Test question', $message->message);
    }

    public function test_ask_stores_assistant_message(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Test answer'),
        ]);

        $user = User::factory()->create();
        $result = $this->service->ask('Test question', $user);

        $message = AIConversation::where('session_id', $result['session_id'])
            ->where('role', 'assistant')
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Test answer', $message->message);
    }

    public function test_ask_filters_context(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Test answer'),
        ]);

        $user = User::factory()->create();
        $context = [
            'contract_id' => 123,
            'invalid_param' => 'should be filtered',
        ];

        $result = $this->service->ask('Test question', $user, 'contracts', $context);

        $message = AIConversation::where('session_id', $result['session_id'])
            ->where('role', 'user')
            ->first();

        $this->assertArrayHasKey('contract_id', $message->metadata['context']);
        $this->assertArrayNotHasKey('invalid_param', $message->metadata['context']);
    }

    public function test_ask_handles_openai_failure(): void
    {
        OpenAI::fake([
            new \Exception('OpenAI API error'),
        ]);

        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->service->ask('Test question', $user);
    }

    public function test_chat_uses_existing_session(): void
    {
        OpenAI::fake([
            $this->fakeResponse('First answer'),
            $this->fakeResponse('Second answer'),
        ]);

        $user = User::factory()->create();
        $sessionId = '11111111-1111-1111-1111-111111111111';

        $this->service->chat('First message', $user, $sessionId);
        $result = $this->service->chat('Second message', $user, $sessionId);

        $this->assertEquals($sessionId, $result['session_id']);

        $messages = AIConversation::where('session_id', $sessionId)
            ->where('is_summary', false)
            ->count();

        $this->assertGreaterThanOrEqual(4, $messages); // 2 user + 2 assistant
    }

    public function test_chat_creates_new_session_if_missing(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Test answer'),
        ]);

        $user = User::factory()->create();

        $result = $this->service->chat('Test message', $user, null);

        $this->assertArrayHasKey('session_id', $result);
        $this->assertNotEmpty($result['session_id']);
    }

    public function test_chat_includes_history(): void
    {
        config([
            'ai_assistant.chat.tail_messages' => 6,
            'ai_assistant.history_window' => 6,
        ]);

        OpenAI::fake([
            $this->fakeResponse('First answer'),
            $this->fakeResponse('Second answer'),
        ]);

        $user = User::factory()->create();
        $sessionId = '11111111-1111-1111-1111-111111111111';

        $this->service->chat('First message', $user, $sessionId);
        $result = $this->service->chat('Second message', $user, $sessionId);

        $this->assertArrayHasKey('message', $result);
    }

    public function test_chat_includes_summary(): void
    {
        config([
            'ai_assistant.chat.summary_every' => 4,
            'ai_assistant.chat.summary_window' => 2,
            'ai_assistant.chat.tail_messages' => 6,
        ]);

        OpenAI::fake([
            $this->fakeResponse('First answer'),
            $this->fakeResponse('Second answer'),
            $this->fakeResponse('Summary'),
        ]);

        $user = User::factory()->create();
        $sessionId = '11111111-1111-1111-1111-111111111111';

        $this->service->chat('First message', $user, $sessionId);
        $this->service->chat('Second message', $user, $sessionId);

        $summary = AIConversation::where('session_id', $sessionId)
            ->where('is_summary', true)
            ->first();

        $this->assertNotNull($summary);
    }

    public function test_chat_creates_summary_after_threshold(): void
    {
        config([
            'ai_assistant.chat.summary_every' => 4,
            'ai_assistant.chat.summary_window' => 2,
        ]);

        OpenAI::fake([
            $this->fakeResponse('Answer 1'),
            $this->fakeResponse('Answer 2'),
            $this->fakeResponse('Summary'),
        ]);

        $user = User::factory()->create();
        $sessionId = '11111111-1111-1111-1111-111111111111';

        $this->service->chat('Message 1', $user, $sessionId);
        $this->service->chat('Message 2', $user, $sessionId);

        $summaryCount = AIConversation::where('session_id', $sessionId)
            ->where('is_summary', true)
            ->count();

        $this->assertGreaterThanOrEqual(1, $summaryCount);
    }

    public function test_chat_respects_history_window(): void
    {
        config([
            'ai_assistant.chat.tail_messages' => 2,
            'ai_assistant.history_window' => 2,
        ]);

        OpenAI::fake([
            $this->fakeResponse('Answer 1'),
            $this->fakeResponse('Answer 2'),
            $this->fakeResponse('Answer 3'),
        ]);

        $user = User::factory()->create();
        $sessionId = '11111111-1111-1111-1111-111111111111';

        $this->service->chat('Message 1', $user, $sessionId);
        $this->service->chat('Message 2', $user, $sessionId);
        $this->service->chat('Message 3', $user, $sessionId);

        $messages = AIConversation::where('session_id', $sessionId)
            ->where('is_summary', false)
            ->count();

        $this->assertGreaterThanOrEqual(3, $messages);
    }

    public function test_listSessions_returns_paginated_results(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Answer'),
            $this->fakeResponse('Answer 2'),
        ]);

        $user = User::factory()->create();
        $this->service->ask('Question 1', $user);
        $this->service->ask('Question 2', $user);

        $sessions = $this->service->listSessions($user, null, 10);

        $this->assertGreaterThanOrEqual(2, $sessions->total());
    }

    public function test_listSessions_filters_by_section(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Answer'),
            $this->fakeResponse('Answer'),
        ]);

        $user = User::factory()->create();
        
        // Grant required permissions for sections
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'contracts.view', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'units.view', 'guard_name' => 'web']);
        $user->givePermissionTo('contracts.view');
        $user->givePermissionTo('units.view');
        
        $this->service->ask('Question 1', $user, 'contracts');
        $this->service->ask('Question 2', $user, 'units');

        $sessions = $this->service->listSessions($user, 'contracts', 10);

        $this->assertGreaterThanOrEqual(1, $sessions->total());
    }

    public function test_deleteSession_deletes_all_messages(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $user = User::factory()->create();
        $result = $this->service->ask('Question', $user);
        $sessionId = $result['session_id'];

        $deleted = $this->service->deleteSession($user, $sessionId);

        $this->assertGreaterThan(0, $deleted);

        $messages = AIConversation::where('session_id', $sessionId)->count();
        $this->assertEquals(0, $messages);
    }

    public function test_availableSections_filters_by_capabilities(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', ['contracts.view']);

        $sections = $this->service->availableSections($user);

        $this->assertIsArray($sections);
        $hasContracts = false;
        foreach ($sections as $section) {
            if (isset($section['label']) && $section['label'] === 'Contracts') {
                $hasContracts = true;
                break;
            }
        }
        $this->assertTrue($hasContracts);
    }

    public function test_suggestions_returns_section_suggestions(): void
    {
        $suggestions = $this->service->suggestions('contracts');

        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
    }

    public function test_ask_handles_empty_response_text(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'id' => 'resp_123',
                'output' => [],
            ]),
        ]);

        $user = User::factory()->create();

        $result = $this->service->ask('Test question', $user);

        $this->assertArrayHasKey('message', $result);
        $this->assertIsString($result['message']);
    }

    public function test_chat_handles_empty_history(): void
    {
        config([
            'ai_assistant.chat.summary_every' => 100,
        ]);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $user = User::factory()->create();
        $sessionId = '11111111-1111-1111-1111-111111111111';

        $result = $this->service->chat('First message', $user, $sessionId);

        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('Answer', $result['message']);
    }

    public function test_listSessions_handles_empty_results(): void
    {
        $user = User::factory()->create();

        $sessions = $this->service->listSessions($user);

        $this->assertEquals(0, $sessions->total());
    }

    public function test_deleteSession_returns_zero_for_nonexistent_session(): void
    {
        $user = User::factory()->create();

        $deleted = $this->service->deleteSession($user, 'nonexistent-session-id');

        $this->assertEquals(0, $deleted);
    }

    public function test_availableSections_returns_empty_for_no_capabilities(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('capabilities', []);
        config(['ai_capabilities.bootstrap_role_map.default' => []]);

        $sections = $this->service->availableSections($user);

        $this->assertIsArray($sections);
        // Should only return sections with no required capabilities
        foreach ($sections as $section) {
            $required = $section['required_capabilities'] ?? [];
            $this->assertEmpty($required);
        }
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
