<?php

namespace Tests\Feature\AI;

use App\Models\AssistantKnowledgeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userWithoutPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // Admin user (has manage-ai-knowledge permission)
        $this->admin = User::factory()->create(['type' => 'admin']);
        $this->admin->assignRole('admin');

        // Regular user without manage-ai-knowledge permission
        $this->userWithoutPermission = User::factory()->create(['type' => 'sales']);
        $this->userWithoutPermission->assignRole('sales');
    }

    // ===== Authorization Tests =====

    public function test_knowledge_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/assistant/knowledge');
        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_knowledge(): void
    {
        $response = $this->actingAs($this->userWithoutPermission)
            ->getJson('/api/ai/assistant/knowledge');

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_create_knowledge(): void
    {
        $response = $this->actingAs($this->userWithoutPermission)
            ->postJson('/api/ai/assistant/knowledge', [
                'module' => 'general',
                'title' => 'Test Entry',
                'content_md' => 'Test content',
                'language' => 'en',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_update_knowledge(): void
    {
        $entry = AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Test Entry',
            'content_md' => 'Test content',
            'language' => 'en',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userWithoutPermission)
            ->putJson("/api/ai/assistant/knowledge/{$entry->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_delete_knowledge(): void
    {
        $entry = AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Test Entry',
            'content_md' => 'Test content',
            'language' => 'en',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userWithoutPermission)
            ->deleteJson("/api/ai/assistant/knowledge/{$entry->id}");

        $response->assertStatus(403);
    }

    // ===== CRUD Operations Tests (Admin) =====

    public function test_admin_can_list_knowledge_entries(): void
    {
        // Create some entries
        $entry1 = AssistantKnowledgeEntry::create([
            'module' => 'sales',
            'title' => 'Sales Guide',
            'content_md' => 'How to sell...',
            'language' => 'en',
            'is_active' => true,
            'priority' => 50,
        ]);

        $entry2 = AssistantKnowledgeEntry::create([
            'module' => 'contracts',
            'title' => 'Contract Guide',
            'content_md' => 'How to create contracts...',
            'language' => 'en',
            'is_active' => true,
            'priority' => 100,
        ]);

        // Verify entries were created
        $this->assertDatabaseHas('assistant_knowledge_entries', ['id' => $entry1->id]);
        $this->assertDatabaseHas('assistant_knowledge_entries', ['id' => $entry2->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/ai/assistant/knowledge');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $data = $response->json('data');
        $total = $response->json('meta.total');
        
        $this->assertEquals(2, $total);
        $this->assertCount(2, $data);

        // Check ordering by priority (50 comes before 100)
        $this->assertEquals('Sales Guide', $data[0]['title']);
        $this->assertEquals('Contract Guide', $data[1]['title']);
    }

    public function test_admin_can_filter_knowledge_by_module(): void
    {
        AssistantKnowledgeEntry::create([
            'module' => 'sales',
            'title' => 'Sales Guide',
            'content_md' => 'Content',
            'language' => 'en',
            'is_active' => true,
        ]);

        AssistantKnowledgeEntry::create([
            'module' => 'contracts',
            'title' => 'Contract Guide',
            'content_md' => 'Content',
            'language' => 'en',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/ai/assistant/knowledge?module=sales');

        $response->assertStatus(200);

        $data = $response->json('data');
        $total = $response->json('meta.total');

        $this->assertEquals(1, $total);
        $this->assertCount(1, $data);
        $this->assertEquals('Sales Guide', $data[0]['title']);
    }

    public function test_admin_can_filter_knowledge_by_language(): void
    {
        AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'English Guide',
            'content_md' => 'Content',
            'language' => 'en',
            'is_active' => true,
        ]);

        AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Arabic Guide',
            'content_md' => 'Content',
            'language' => 'ar',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/ai/assistant/knowledge?language=ar');

        $response->assertStatus(200);

        $data = $response->json('data');
        $total = $response->json('meta.total');

        $this->assertEquals(1, $total);
        $this->assertCount(1, $data);
        $this->assertEquals('Arabic Guide', $data[0]['title']);
    }

    public function test_admin_can_filter_knowledge_by_active_status(): void
    {
        AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Active Entry',
            'content_md' => 'Content',
            'language' => 'en',
            'is_active' => true,
        ]);

        AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Inactive Entry',
            'content_md' => 'Content',
            'language' => 'en',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/ai/assistant/knowledge?is_active=false');

        $response->assertStatus(200);

        $data = $response->json('data');
        $total = $response->json('meta.total');

        $this->assertEquals(1, $total);
        $this->assertCount(1, $data);
        $this->assertEquals('Inactive Entry', $data[0]['title']);
    }

    public function test_admin_can_create_knowledge_entry(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/knowledge', [
                'module' => 'sales',
                'page_key' => 'sales.reservations.index',
                'title' => 'How to Create a Reservation',
                'content_md' => '## Steps\n1. Go to reservations page\n2. Click Create',
                'tags' => ['reservation', 'sales', 'guide'],
                'roles' => ['sales', 'sales_leader'],
                'permissions' => ['sales.reservations.create'],
                'language' => 'en',
                'is_active' => true,
                'priority' => 50,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Knowledge entry created successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'module',
                    'page_key',
                    'title',
                    'content_md',
                    'tags',
                    'roles',
                    'permissions',
                    'language',
                    'is_active',
                    'priority',
                    'updated_by',
                ],
            ]);

        $this->assertDatabaseHas('assistant_knowledge_entries', [
            'module' => 'sales',
            'title' => 'How to Create a Reservation',
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_knowledge_entry(): void
    {
        $entry = AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'Original Title',
            'content_md' => 'Original content',
            'language' => 'en',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/ai/assistant/knowledge/{$entry->id}", [
                'title' => 'Updated Title',
                'content_md' => 'Updated content',
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Knowledge entry updated successfully.',
            ]);

        $this->assertDatabaseHas('assistant_knowledge_entries', [
            'id' => $entry->id,
            'title' => 'Updated Title',
            'content_md' => 'Updated content',
            'is_active' => false,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_delete_knowledge_entry(): void
    {
        $entry = AssistantKnowledgeEntry::create([
            'module' => 'general',
            'title' => 'To Be Deleted',
            'content_md' => 'Content',
            'language' => 'en',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/ai/assistant/knowledge/{$entry->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Knowledge entry deleted successfully.',
            ]);

        $this->assertDatabaseMissing('assistant_knowledge_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_update_nonexistent_entry_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/ai/assistant/knowledge/99999', [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Knowledge entry not found.',
            ]);
    }

    public function test_delete_nonexistent_entry_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/ai/assistant/knowledge/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Knowledge entry not found.',
            ]);
    }

    // ===== Validation Tests =====

    public function test_create_requires_module(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/knowledge', [
                'title' => 'Test Entry',
                'content_md' => 'Content',
                'language' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module']);
    }

    public function test_create_requires_title(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/knowledge', [
                'module' => 'general',
                'content_md' => 'Content',
                'language' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_requires_content_md(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/knowledge', [
                'module' => 'general',
                'title' => 'Test Entry',
                'language' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_md']);
    }

    public function test_create_requires_language(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/knowledge', [
                'module' => 'general',
                'title' => 'Test Entry',
                'content_md' => 'Content',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    }

    public function test_create_validates_language_values(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/knowledge', [
                'module' => 'general',
                'title' => 'Test Entry',
                'content_md' => 'Content',
                'language' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    }

    public function test_create_validates_module_max_length(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ai/assistant/knowledge', [
                'module' => str_repeat('a', 121),
                'title' => 'Test Entry',
                'content_md' => 'Content',
                'language' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module']);
    }
}

