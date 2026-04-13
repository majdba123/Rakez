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
        $this->assertSame('denied', $out['result']['status'] ?? null);
        $this->assertFalse($out['result']['allowed']);
        $this->assertSame('use-ai-assistant', $out['result']['required_permission']);
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

    public function test_every_registered_tool_has_an_explicit_gate_configuration(): void
    {
        $registry = app(ToolRegistry::class);
        $toolGates = config('ai_assistant.v2.tool_gates', []);

        foreach ($registry->registeredNames() as $toolName) {
            $this->assertArrayHasKey($toolName, $toolGates, "Tool [{$toolName}] must have an explicit gate config entry.");
        }
    }

    public function test_hiring_advisor_requires_hr_dashboard_view_by_default(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('hr.dashboard.view', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $registry = app(ToolRegistry::class);

        $this->assertNotContains('tool_hiring_advisor', $registry->allowedToolNamesForUser($user->fresh()));

        $user->givePermissionTo('hr.dashboard.view');

        $this->assertContains('tool_hiring_advisor', $registry->allowedToolNamesForUser($user->fresh()));
    }

    public function test_registered_tool_without_explicit_gate_is_denied_by_default(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        config([
            'ai_assistant.v2.tool_gates' => [
                'tool_search_records' => ['permission' => 'use-ai-assistant'],
            ],
        ]);

        $registry = app(ToolRegistry::class);

        $this->assertNotContains('tool_kpi_sales', $registry->allowedToolNamesForUser($user->fresh()));
    }

    public function test_registered_tool_count_matches_registry(): void
    {
        $registry = app(ToolRegistry::class);
        $this->assertCount(13, $registry->registeredNames());
    }
}
