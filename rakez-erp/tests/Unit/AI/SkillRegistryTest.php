<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\CapabilityResolver;
use App\Services\AI\Skills\SkillRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SkillRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_aliases_are_normalized_in_skill_definitions(): void
    {
        config([
            'ai_skills.section_aliases' => ['finance' => 'accounting'],
            'ai_skills.definitions' => [
                'accounting.finance_snapshot' => [
                    'skill_key' => 'accounting.finance_snapshot',
                    'section_key' => 'finance',
                    'required_permissions' => [],
                    'required_capabilities' => [],
                    'handler' => \App\Services\AI\Skills\Handlers\ToolBackedSkillHandler::class,
                    'formatter' => \App\Services\AI\Skills\Formatting\DefaultSkillFormatter::class,
                    'tool_name' => 'tool_finance_calculator',
                ],
            ],
        ]);

        $registry = app(SkillRegistry::class);
        $definition = $registry->find('accounting.finance_snapshot');

        $this->assertNotNull($definition);
        $this->assertSame('accounting', $definition['section_key']);
    }

    public function test_available_for_user_respects_permissions_and_section_gate(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('sales.dashboard.view', 'web');
        Permission::findOrCreate('contracts.view', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');

        $registry = app(SkillRegistry::class);
        $capabilities = app(CapabilityResolver::class)->resolve($user->fresh());
        $skills = $registry->availableForUser($user->fresh(), $capabilities);
        $keys = array_values(array_map(fn (array $skill) => $skill['skill_key'], $skills));

        $this->assertContains('sales.kpi_snapshot', $keys);
        $this->assertNotContains('contracts.project_summary', $keys);
    }

    public function test_disabled_feature_flag_hides_skill(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('sales.dashboard.view', 'web');

        config(['ai_skills.flags.sales_kpi_snapshot' => false]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');

        $registry = app(SkillRegistry::class);
        $capabilities = app(CapabilityResolver::class)->resolve($user->fresh());
        $skills = $registry->availableForUser($user->fresh(), $capabilities);
        $keys = array_values(array_map(fn (array $skill) => $skill['skill_key'], $skills));

        $this->assertNotContains('sales.kpi_snapshot', $keys);
    }
}
