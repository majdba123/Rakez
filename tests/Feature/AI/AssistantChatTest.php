<?php

namespace Tests\Feature\AI;

use App\Models\AssistantConversation;
use App\Models\AssistantKnowledgeEntry;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\AI\AssistantLLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userWithPermission;
    protected User $userWithoutPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // Admin user (has all permissions including use-ai-assistant)
        $this->admin = User::factory()->create(['type' => 'admin']);
        $this->admin->assignRole('admin');

        // User with use-ai-assistant permission
        $this->userWithPermission = User::factory()->create(['type' => 'sales']);
        $this->userWithPermission->assignRole('sales');

        // User without use-ai-assistant permission (created without role assignment)
        $this->userWithoutPermission = User::factory()->create(['type' => 'user']);
        // Explicitly ensure this user has NO permissions
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===== Authentication & Authorization Tests =====

    public function test_chat_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/assistant/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_chat(): void
    {
        $response = $this->actingAs($this->userWithoutPermission)
            ->postJson('/api/ai/assistant/chat', [
                'message' => 'How do I create a contract?',
            ]);

        $response->assertStatus(403);
        $message = $response->json('message') ?? '';
        $this->assertStringContainsString('permission', strtolower($message), 'Response message should mention permission');
    }

    public function test_user_with_permission_can_access_chat(): void
    {
        // Mock the LLM service
        $mockLLMService = Mockery::mock(AssistantLLMService::class);
        $mockLLMService->shouldReceive('generateAnswer')
            ->once()
            ->andReturn([
                'answer' => 'Here is how to create a contract...',
                'tokens' => 150,
                'latency_ms' => 500,
            ]);

        $this->app->instance(AssistantLLMService::class, $mockLLMService);

        $response = $this->actingAs($this->userWithPermission)
            ->postJson('/api/ai/assistant/chat', [
                'message' => 'How do I create a contract?',
                'language' => 'en',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'conversation_id',
                    'reply',
                    'knowledge_used_count',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'reply' => 'Here is how to create a contract...',
                ],
            ]);

        // Verify conversation was created
        $this->assertDatabaseHas('assistant_conversations', [
            'user_id' => $this->userWithPermission->id,
        ]);

        // Verify messages were logged
        $conversationId = $response->json('data.conversation_id');
        $this->assertDatabaseHas('assistant_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'How do I create a contract?',
        ]);
        $this->assertDatabaseHas('assistant_messages', [
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'capability_used' => 'assistant_help',
        ]);
    }

    // ===== Knowledge Filtering by Permissions Tests =====

    public function test_knowledge_filtering_excludes_restricted_entries(): void
    {
        // Create an open knowledge entry (no role/permission restrictions)
        $openEntry = AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Open Entry',
            'content_md' => 'This is publicly available information.',
            'language' => 'en',
            'is_active' => true,
            'priority' => 100,
        ]);

        // Create a restricted knowledge entry (requires admin role)
        $restrictedEntry = AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Restricted Entry',
            'content_md' => 'This is admin-only information.',
            'roles' => ['admin'],
            'language' => 'en',
            'is_active' => true,
            'priority' => 100,
        ]);

        // Mock the LLM service and capture the knowledge snippets passed to it
        $capturedSnippets = null;
        $mockLLMService = Mockery::mock(AssistantLLMService::class);
        $mockLLMService->shouldReceive('generateAnswer')
            ->once()
            ->withArgs(function ($systemPrompt, $knowledgeSnippets, $userMessage, $userContext) use (&$capturedSnippets) {
                $capturedSnippets = $knowledgeSnippets;
                return true;
            })
            ->andReturn([
                'answer' => 'Response based on available knowledge.',
                'tokens' => 100,
                'latency_ms' => 300,
            ]);

        $this->app->instance(AssistantLLMService::class, $mockLLMService);

        // Act as a sales user (not admin)
        $response = $this->actingAs($this->userWithPermission)
            ->postJson('/api/ai/assistant/chat', [
                'message' => 'Tell me about the system.',
                'language' => 'en',
            ]);

        $response->assertStatus(200);

        // Assert that only the open entry was included, not the restricted one
        $this->assertNotNull($capturedSnippets);
        $this->assertCount(1, $capturedSnippets);
        $this->assertEquals('Open Entry', $capturedSnippets[0]['title']);

        // The restricted entry should NOT be in the snippets
        $titles = array_column($capturedSnippets, 'title');
        $this->assertNotContains('Restricted Entry', $titles);
    }

    public function test_admin_can_see_all_knowledge_entries(): void
    {
        // Create an open knowledge entry
        AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Open Entry',
            'content_md' => 'This is publicly available information.',
            'language' => 'en',
            'is_active' => true,
            'priority' => 100,
        ]);

        // Create a restricted knowledge entry (requires admin role)
        AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Admin Entry',
            'content_md' => 'This is admin-only information.',
            'roles' => ['admin'],
            'language' => 'en',
            'is_active' => true,
            'priority' => 100,
        ]);

        // Mock the LLM service and capture the knowledge snippets
        $capturedSnippets = null;
        $mockLLMService = Mockery::mock(AssistantLLMService::class);
        $mockLLMService->shouldReceive('generateAnswer')
            ->once()
            ->withArgs(function ($systemPrompt, $knowledgeSnippets, $userMessage, $userContext) use (&$capturedSnippets) {
                $capturedSnippets = $knowledgeSnippets;
                return true;
            })
            ->andReturn([
                'answer' => 'Admin response.',
                'tokens' => 100,
                'latency_ms' => 300,
            ]);

        $this->app->instance(AssistantLLMService::class, $mockLLMService);

        // Act as admin
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/chat', [
                'message' => 'Tell me about the system.',
                'language' => 'en',
            ]);

        $response->assertStatus(200);

        // Admin should see both entries
        $this->assertNotNull($capturedSnippets);
        $this->assertCount(2, $capturedSnippets);
        $titles = array_column($capturedSnippets, 'title');
        $this->assertContains('Open Entry', $titles);
        $this->assertContains('Admin Entry', $titles);
    }

    public function test_permission_based_knowledge_filtering(): void
    {
        // Create entry that requires a specific permission
        AssistantKnowledgeEntry::create([
            'module' => 'contracts',
            'title' => 'Contract Creation Guide',
            'content_md' => 'How to create contracts...',
            'permissions' => ['contracts.create'],
            'language' => 'en',
            'is_active' => true,
            'priority' => 100,
        ]);

        // Sales user does NOT have contracts.create permission
        $capturedSnippets = null;
        $mockLLMService = Mockery::mock(AssistantLLMService::class);
        $mockLLMService->shouldReceive('generateAnswer')
            ->once()
            ->withArgs(function ($systemPrompt, $knowledgeSnippets, $userMessage, $userContext) use (&$capturedSnippets) {
                $capturedSnippets = $knowledgeSnippets;
                return true;
            })
            ->andReturn([
                'answer' => 'No relevant information found.',
                'tokens' => 50,
                'latency_ms' => 200,
            ]);

        $this->app->instance(AssistantLLMService::class, $mockLLMService);

        $response = $this->actingAs($this->userWithPermission)
            ->postJson('/api/ai/assistant/chat', [
                'message' => 'How do I create a contract?',
                'module' => 'contracts',
                'language' => 'en',
            ]);

        $response->assertStatus(200);

        // Sales user should NOT see the contract creation guide
        $this->assertNotNull($capturedSnippets);
        $this->assertCount(0, $capturedSnippets);
    }

    // ===== Conversation Continuity Tests =====

    public function test_can_continue_existing_conversation(): void
    {
        // Create a conversation
        $conversation = AssistantConversation::create([
            'user_id' => $this->userWithPermission->id,
            'context' => ['module' => 'general', 'language' => 'en'],
        ]);

        $mockLLMService = Mockery::mock(AssistantLLMService::class);
        $mockLLMService->shouldReceive('generateAnswer')
            ->once()
            ->andReturn([
                'answer' => 'Continuing our conversation...',
                'tokens' => 100,
                'latency_ms' => 300,
            ]);

        $this->app->instance(AssistantLLMService::class, $mockLLMService);

        $response = $this->actingAs($this->userWithPermission)
            ->postJson('/api/ai/assistant/chat', [
                'message' => 'Follow up question',
                'conversation_id' => $conversation->id,
                'language' => 'en',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'conversation_id' => $conversation->id,
                ],
            ]);

        // Verify messages were added to existing conversation
        $this->assertEquals(2, AssistantMessage::where('conversation_id', $conversation->id)->count());
    }

    // ===== Validation Tests =====

    public function test_message_is_required(): void
    {
        $response = $this->actingAs($this->userWithPermission)
            ->postJson('/api/ai/assistant/chat', [
                'language' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_message_max_length(): void
    {
        $response = $this->actingAs($this->userWithPermission)
            ->postJson('/api/ai/assistant/chat', [
                'message' => str_repeat('a', 6001),
                'language' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_invalid_language_rejected(): void
    {
        $response = $this->actingAs($this->userWithPermission)
            ->postJson('/api/ai/assistant/chat', [
                'message' => 'Hello',
                'language' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    }
}

