<?php

namespace Tests\Feature\AI;

use App\Models\Contract;
use App\Models\User;
use App\Models\AIConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\TestsWithAI;

class AIAssistantIntegrationTest extends TestCase
{
    use RefreshDatabase, TestsWithAI;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permissions exist
        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('contracts.view', 'web');
        Permission::findOrCreate('marketing.projects.view', 'web');
        Permission::findOrCreate('dashboard.analytics.view', 'web');
    }

    /**
     * Test multi-turn chat persistence and history window.
     */
    public function test_multi_turn_chat_history_persistence(): void
    {
        $user = $this->createAiUser(['type' => 'admin']);
        Sanctum::actingAs($user);

        $sessionId = '11111111-1111-1111-1111-111111111111';

        // 1. First message
        OpenAI::fake([
            CreateResponse::fake([
                'id' => 'resp_1',
                'model' => 'gpt-4',
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'First response',
                                'annotations' => [],
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                    'total_tokens' => 15,
                ]
            ]),
        ]);

        $response1 = $this->postJson('/api/ai/chat', [
            'message' => 'Hello AI',
            'session_id' => $sessionId,
        ]);

        $response1->assertOk();
        $this->assertDatabaseHas('ai_conversations', [
            'session_id' => $sessionId,
            'message' => 'Hello AI',
            'role' => 'user'
        ]);

        // Check that assistant message contains expected text (may include additional content)
        $assistantMessage = AIConversation::where('session_id', $sessionId)
            ->where('role', 'assistant')
            ->first();
        $this->assertNotNull($assistantMessage);
        $this->assertStringContainsString('First response', $assistantMessage->message);

        // 2. Second message
        OpenAI::fake([
            CreateResponse::fake([
                'id' => 'resp_2',
                'model' => 'gpt-4',
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_2',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'I am fine, thanks for asking!',
                                'annotations' => [],
                            ],
                        ],
                    ],
                ]
            ])
        ]);

        $response2 = $this->postJson('/api/ai/chat', [
            'message' => 'How are you?',
            'session_id' => $sessionId,
        ]);

        $response2->assertOk();

        // Check that response contains expected text (may include additional content)
        $message2 = $response2->json('data.message');
        $this->assertStringContainsString('I am fine, thanks for asking!', $message2);

        // Verify that history was indeed sent to OpenAI
        OpenAI::assertSent(\OpenAI\Resources\Responses::class, function (string $method, array $parameters) {
            $messages = $parameters['input'];
            $hasFirstUserMsg = collect($messages)->contains(fn($m) => $m['role'] === 'user' && $m['content'] === 'Hello AI');
            $hasFirstAssistantMsg = collect($messages)->contains(fn($m) => $m['role'] === 'assistant' && str_contains($m['content'], 'First response'));
            $hasSecondUserMsg = collect($messages)->contains(fn($m) => $m['role'] === 'user' && $m['content'] === 'How are you?');

            return $hasFirstUserMsg && $hasFirstAssistantMsg && $hasSecondUserMsg;
        });
    }

    /**
     * Test context awareness for contracts.
     */
    public function test_contract_context_awareness(): void
    {
        $user = $this->createAiUser(['type' => 'admin']);
        $user->givePermissionTo('contracts.view');
        Sanctum::actingAs($user);

        $contract = Contract::factory()->create([
            'user_id' => $user->id,
            'project_name' => 'Secret Project Alpha',
            'status' => 'approved'
        ]);

        OpenAI::fake([
            CreateResponse::fake([
                'id' => 'resp_3',
                'model' => 'gpt-4',
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_3',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'I see your project Alpha is approved.',
                                'annotations' => [],
                            ],
                        ],
                    ],
                ]
            ])
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'What is the status of my project?',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id
            ]
        ]);

        $response->assertOk();

        // Check that response contains expected text (may include additional content)
        $message = $response->json('data.message');
        $this->assertStringContainsString('I see your project Alpha is approved.', $message);

        // Verify contract details were in the instructions (system prompt)
        OpenAI::assertSent(\OpenAI\Resources\Responses::class, function (string $method, array $parameters) {
            $instructions = $parameters['instructions'];
            return str_contains($instructions, 'Secret Project Alpha') && str_contains($instructions, 'approved');
        });
    }

    /**
     * Test capability-based access control - denying access without permission
     */
    public function test_capability_based_section_access_denies_without_permission(): void
    {
        // User must have use-ai-assistant to pass route middleware; no contracts.view so service returns UNAUTHORIZED_SECTION
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create(['type' => 'test_no_permissions']);
        $user->givePermissionTo('use-ai-assistant');
        $user->syncPermissions(['use-ai-assistant']); // only this permission

        config(['ai_capabilities.bootstrap_role_map.test_no_permissions' => []]);
        config(['ai_capabilities.bootstrap_role_map.default' => []]);

        Sanctum::actingAs($user);

        // OpenAI should NOT be called
        OpenAI::fake();

        // Try to access contracts section (requires contracts.view)
        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Tell me about contracts',
            'section' => 'contracts'
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'UNAUTHORIZED_SECTION');

        OpenAI::assertNotSent(\OpenAI\Resources\Responses::class);
    }

    /**
     * Test assistant disabled path.
     */
    public function test_assistant_disabled_returns_503(): void
    {
        config(['ai_assistant.enabled' => false]);

        $user = $this->createAiUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Hello',
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('error_code', 'ai_disabled');
    }

    /**
     * Test delete session belonging to another user.
     */
    public function test_delete_session_belonging_to_another_user_denied(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user2->givePermissionTo('use-ai-assistant'); // pass route middleware so controller returns UNAUTHORIZED_SESSION_ACCESS

        $sessionId = (string) \Illuminate\Support\Str::uuid();

        AIConversation::create([
            'user_id' => $user1->id,
            'session_id' => $sessionId,
            'role' => 'user',
            'message' => 'User 1 message',
        ]);

        Sanctum::actingAs($user2);

        $response = $this->deleteJson("/api/ai/conversations/{$sessionId}");

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'UNAUTHORIZED_SESSION_ACCESS');

        $this->assertDatabaseHas('ai_conversations', ['session_id' => $sessionId]);
    }

    /**
     * Test throttle behavior.
     * Skipped: throttle cannot be tested reliably without mocking the rate limiter;
     * enable when implemented with a stable mock or in a dedicated throttle test.
     */
    public function test_throttle_ai_assistant(): void
    {
        $this->markTestSkipped('Throttle testing requires rate-limiter mock; skipped to avoid flakiness.');
    }

    /**
     * Test capability-based access control - allowing access with permission
     */
    public function test_capability_based_section_access_allows_with_permission(): void
    {
        // Create user WITH the required permission
        $user = $this->createAiUser(['type' => 'test_with_permissions']);
        $user->givePermissionTo('contracts.view');

        Sanctum::actingAs($user);

        OpenAI::fake([
            CreateResponse::fake([
                'id' => 'resp_5',
                'model' => 'gpt-4',
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_5',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'Contract info',
                                'annotations' => [],
                            ],
                        ],
                    ],
                ]
            ])
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Tell me about contracts',
            'section' => 'contracts'
        ]);

        $response->assertOk();
    }

    /**
     * Test budget enforcement.
     */
    public function test_budget_enforcement(): void
    {
        $user = $this->createAiUser();
        Sanctum::actingAs($user);

        // Set a very low budget for testing
        config(['ai_assistant.budgets.per_user_daily_tokens' => 10]);

        // 1. Create a message that uses tokens
        AIConversation::create([
            'user_id' => $user->id,
            'session_id' => '11111111-1111-1111-1111-111111111111',
            'role' => 'assistant',
            'message' => 'Previous response',
            'total_tokens' => 15, // Already over budget
            'created_at' => now(),
        ]);

        // 2. Try to ask a new question
        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Will this work?',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('error_code', 'ai_budget_exceeded');
    }

    /**
     * Test session deletion.
     */
    public function test_session_lifecycle_and_deletion(): void
    {
        $user = $this->createAiUser();
        Sanctum::actingAs($user);

        $sessionId = '11111111-1111-1111-1111-111111111111';

        AIConversation::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'user',
            'message' => 'Hello',
        ]);

        // Verify it exists
        $this->assertDatabaseHas('ai_conversations', ['session_id' => $sessionId]);

        // Delete it
        $response = $this->deleteJson("/api/ai/conversations/{$sessionId}");

        $response->assertOk();
        $response->assertJsonPath('data.deleted', 1);

        // Verify it's gone
        $this->assertDatabaseMissing('ai_conversations', ['session_id' => $sessionId]);
    }

    private function createAiUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->givePermissionTo('use-ai-assistant');

        return $user;
    }
}
