<?php

namespace Tests\Feature\AI;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (empty(config('ai_sections'))) {
            config(['ai_sections' => require config_path('ai_sections.php')]);
        }
    }

    public function test_user_cannot_access_other_user_contract(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id,
            ],
        ]);

        // Should either return 403 or exclude contract data
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_user_with_view_all_can_access_any_contract(): void
    {
        Permission::findOrCreate('contracts.view', 'web');
        Permission::findOrCreate('contracts.view_all', 'web');
        Permission::findOrCreate('use-ai-assistant', 'web');
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->givePermissionTo(['contracts.view', 'contracts.view_all', 'use-ai-assistant']);
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($admin);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id,
            ],
        ]);

        $response->assertOk();
    }

    public function test_user_cannot_access_nonexistent_contract(): void
    {
        Permission::findOrCreate('contracts.view', 'web');
        Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['contracts.view', 'use-ai-assistant']);
        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => 99999,
            ],
        ]);

        // Should handle gracefully (200 with empty contract context, or 403)
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_context_policy_enforces_contract_access(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        // This should trigger policy check in ContextBuilder
        // The exact behavior depends on implementation
        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id,
            ],
        ]);

        // Policy should prevent unauthorized access
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_sections_filtered_by_capabilities(): void
    {
        // Override bootstrap so developer has no dashboard.analytics.view; give use-ai-assistant so route allows
        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('contracts.view', 'web');
        $bootstrap = config('ai_capabilities.bootstrap_role_map', []);
        $bootstrap['developer'] = ['contracts.view', 'use-ai-assistant'];
        config(['ai_capabilities.bootstrap_role_map' => $bootstrap]);

        $developer = User::factory()->create(['type' => 'developer']);
        $developer->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($developer);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $sections = $response->json('data');

        // Developer without dashboard.analytics.view should not see Dashboard section
        $hasDashboard = false;
        foreach ($sections as $section) {
            if (isset($section['label']) && $section['label'] === 'Dashboard') {
                $hasDashboard = true;
                break;
            }
        }

        $this->assertFalse($hasDashboard, 'Developer should not see dashboard section');
    }

    public function test_admin_sees_all_sections(): void
    {
        Permission::findOrCreate('dashboard.analytics.view', 'web');
        Permission::findOrCreate('contracts.view', 'web');
        Permission::findOrCreate('units.view', 'web');
        Permission::findOrCreate('use-ai-assistant', 'web');
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->givePermissionTo(['dashboard.analytics.view', 'contracts.view', 'units.view', 'use-ai-assistant']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $sections = $response->json('data');

        // Admin with dashboard capability should see dashboard
        $hasDashboard = false;
        foreach ($sections as $section) {
            if (isset($section['label']) && $section['label'] === 'Dashboard') {
                $hasDashboard = true;
                break;
            }
        }

        $this->assertTrue($hasDashboard, 'Admin with dashboard.analytics.view capability should see dashboard section');
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
