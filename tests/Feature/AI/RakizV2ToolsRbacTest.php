<?php

namespace Tests\Feature\AI;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RakizV2ToolsRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Permission::findOrCreate('use-ai-assistant');
        Permission::findOrCreate('marketing.projects.view');
        Permission::findOrCreate('contracts.view');
    }

    public function test_get_lead_summary_tool_denies_without_view_permission(): void
    {
        $lead = Lead::factory()->create(['name' => 'Test Lead']);
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $registry = app(\App\Services\AI\ToolRegistry::class);
        $result = $registry->execute($user, 'tool_get_lead_summary', ['lead_id' => $lead->id]);

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('allowed', $result['result']);
        $this->assertFalse($result['result']['allowed']);
    }

    public function test_get_lead_summary_tool_allows_with_view_permission(): void
    {
        $lead = Lead::factory()->create(['name' => 'Test Lead']);
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.projects.view');
        Sanctum::actingAs($user);

        $registry = app(\App\Services\AI\ToolRegistry::class);
        $result = $registry->execute($user, 'tool_get_lead_summary', ['lead_id' => $lead->id]);

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('summary', $result['result']);
        $this->assertStringContainsString('Test Lead', $result['result']['summary']);
    }

    public function test_get_project_summary_tool_denies_without_contract_view(): void
    {
        $contract = \App\Models\Contract::factory()->create(['project_name' => 'Test Project']);
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $registry = app(\App\Services\AI\ToolRegistry::class);
        $result = $registry->execute($user, 'tool_get_project_summary', ['project_id' => $contract->id]);

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('allowed', $result['result']);
        $this->assertFalse($result['result']['allowed']);
    }
}
