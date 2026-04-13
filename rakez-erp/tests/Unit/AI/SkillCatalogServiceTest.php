<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\Skills\SkillCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SkillCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('sales.dashboard.view', 'web');
        Permission::findOrCreate('marketing.dashboard.view', 'web');
    }

    public function test_catalog_for_user_returns_public_skill_metadata_only(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');

        $catalog = app(SkillCatalogService::class)->catalogForUser($user->fresh());

        $this->assertArrayHasKey('skills', $catalog);
        $this->assertNotEmpty($catalog['skills']);

        $first = $catalog['skills'][0];
        $this->assertArrayHasKey('skill_key', $first);
        $this->assertArrayHasKey('section_key', $first);
        $this->assertArrayHasKey('required_inputs', $first);
        $this->assertArrayNotHasKey('handler', $first);
        $this->assertArrayNotHasKey('formatter', $first);
    }

    public function test_catalog_section_filter_uses_alias_normalization(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');

        $catalog = app(SkillCatalogService::class)->catalogForUser($user->fresh(), 'finance');

        $this->assertSame('accounting', $catalog['section_filter']);
        $this->assertSame(0, $catalog['count']);
    }
}
