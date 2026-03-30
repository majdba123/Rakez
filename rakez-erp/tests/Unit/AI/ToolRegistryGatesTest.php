<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Verifies {@see ToolRegistry::allowedToolNamesForUser} and {@see ToolRegistry::execute}
 * against config('ai_assistant.v2.tool_gates') — no OpenAI calls.
 */
class ToolRegistryGatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_use_ai_assistant_gets_no_tools(): void
    {
        Permission::findOrCreate('contracts.view', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('contracts.view');

        $registry = app(ToolRegistry::class);

        $this->assertSame([], $registry->allowedToolNamesForUser($user));

        $out = $registry->execute($user, 'tool_kpi_sales', []);
        $this->assertSame(['allowed' => false, 'error' => 'Permission denied for this tool'], $out['result']);
    }

    public function test_tool_kpi_sales_requires_sales_dashboard_view_when_gate_configured(): void
    {
        config([
            'ai_assistant.v2.tool_gates' => [
                'tool_kpi_sales' => ['permission' => 'sales.dashboard.view'],
            ],
        ]);

        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('sales.dashboard.view', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $registry = app(ToolRegistry::class);

        $this->assertNotContains('tool_kpi_sales', $registry->allowedToolNamesForUser($user->fresh()));

        $user->givePermissionTo('sales.dashboard.view');

        $this->assertContains('tool_kpi_sales', $registry->allowedToolNamesForUser($user->fresh()));
    }

    public function test_registered_tool_count_matches_registry(): void
    {
        $registry = app(ToolRegistry::class);
        $this->assertCount(17, $registry->registeredNames());
    }
}
