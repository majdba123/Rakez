<?php

namespace Tests\Feature\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RakizV2ExplainAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('use-ai-assistant');
        Permission::findOrCreate('contracts.view');
        Permission::findOrCreate('marketing.projects.view');
    }

    public function test_v2_explain_access_returns_suggested_routes_filtered_by_rbac(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.projects.view');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/v2/explain-access', [
            'route' => '/api/marketing/leads',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'allowed',
                'missing_permissions',
                'human_reason',
                'suggested_routes',
            ],
        ]);
        $suggested = $response->json('data.suggested_routes');
        $this->assertIsArray($suggested);
    }

    public function test_v2_explain_access_requires_route(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/v2/explain-access', []);

        $response->assertStatus(422);
    }
}
