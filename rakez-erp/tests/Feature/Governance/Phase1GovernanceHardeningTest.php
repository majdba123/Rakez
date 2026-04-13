<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\FilamentNavigationPolicy;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\RoleGovernanceService;
use App\Services\Governance\UserGovernanceService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Feature\Auth\BasePermissionTestCase;

class Phase1GovernanceHardeningTest extends BasePermissionTestCase
{
    // ── enabled_sections gating ───────────────────────────────────

    #[Test]
    public function enabled_sections_can_explicitly_disable_business_section_overview_pages(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
        ]);

        $overviewPaths = [
            '/admin/credit-overview',
            '/admin/accounting-overview',
            '/admin/projects-overview',
            '/admin/sales-overview',
            '/admin/hr-overview',
            '/admin/marketing-overview',
            '/admin/inventory-overview',
        ];

        foreach ($overviewPaths as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertForbidden("Path {$path} must be blocked when its section is removed from enabled_sections.");
        }
    }

    #[Test]
    public function enabled_sections_allows_governance_core_groups(): void
    {
        $enabled = config('governance.enabled_sections', []);

        $this->assertContains('Overview', $enabled);
        $this->assertContains('Access Governance', $enabled);
        $this->assertContains('Governance Observability', $enabled);
    }

    #[Test]
    public function disabled_enabled_sections_block_access_governance_and_observability_resources(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');
        $this->actingAs($user);

        config()->set('governance.enabled_sections', ['Overview']);

        $this->assertFalse(\App\Filament\Admin\Resources\Users\UserResource::canAccess());
        $this->assertFalse(\App\Filament\Admin\Resources\Roles\RoleResource::canAccess());
        $this->assertFalse(\App\Filament\Admin\Resources\Permissions\PermissionResource::canAccess());
        $this->assertFalse(\App\Filament\Admin\Resources\DirectPermissions\DirectPermissionResource::canAccess());
        $this->assertFalse(\App\Filament\Admin\Resources\EffectiveAccess\EffectiveAccessResource::canAccess());
        $this->assertFalse(\App\Filament\Admin\Resources\GovernanceAuditLogs\GovernanceAuditLogResource::canAccess());
    }

    // ── Temporary Permissions (feature-flagged; disabled = no Filament surface) ──

    #[Test]
    public function temporary_permissions_resource_is_inaccessible_when_rollout_disabled(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $user = $this->makeGovernanceUser('erp_admin');

        $this->actingAs($user)
            ->get('/admin/governance-temporary-permissions')
            ->assertForbidden();
    }

    // ── Gate::before does not bypass governance permissions ───────

    #[Test]
    public function operational_admin_role_does_not_bypass_governance_gate(): void
    {
        // `admin` is a managed governance overlay role (see config/governance.managed_panel_roles)
        // and receives the full permission set in tests — use a purely operational role instead.
        $user = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $user->assignRole('sales');

        $access = app(GovernanceAccessService::class);

        $this->assertFalse(
            $access->canAccessPanel($user),
            'Operational staff without a governance overlay role must NOT access the panel.',
        );
    }

    #[Test]
    public function soft_deleted_governance_user_cannot_access_panel(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');
        $user->delete();

        $this->assertFalse(
            app(GovernanceAccessService::class)->canAccessPanel(User::withTrashed()->findOrFail($user->id))
        );
    }

    #[Test]
    public function operational_admin_role_still_bypasses_operational_abilities(): void
    {
        $user = User::factory()->create([
            'type' => 'admin',
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);

        $this->assertTrue(
            $user->can('contracts.view'),
            'Operational admin must still bypass operational abilities via Gate::before.',
        );
    }

    #[Test]
    public function gate_before_returns_null_for_governance_abilities(): void
    {
        $user = User::factory()->create([
            'type' => 'admin',
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);

        // admin role has admin.panel.access via Spatie (seeder gives all permissions),
        // and admin is now an allowed managed panel role.
        // Gate::before no longer short-circuits admin.* — it returns null, letting Spatie decide.
        $access = app(GovernanceAccessService::class);

        $this->assertTrue(
            $access->canAccessPanel($user),
            'Admin role should access the panel when it carries admin.panel.access via Spatie.',
        );
    }

    // ── FilamentNavigationPolicy fail-closed ──────────────────────

    #[Test]
    public function navigation_policy_fails_closed_on_misconfigured_group(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');

        config()->set('governance.filament_navigation_group_permissions.Test Broken', 'not-an-array');

        $policy = app(FilamentNavigationPolicy::class);

        $this->assertFalse(
            $policy->canAccessNavigationGroup($user, 'Test Broken'),
            'Misconfigured group permission (non-array) must fail-closed.',
        );
    }

    #[Test]
    public function navigation_policy_fails_closed_on_empty_array_group(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');

        config()->set('governance.filament_navigation_group_permissions.Test Empty', []);

        $policy = app(FilamentNavigationPolicy::class);

        $this->assertFalse(
            $policy->canAccessNavigationGroup($user, 'Test Empty'),
            'Empty permission array for a configured group must fail-closed.',
        );
    }

    #[Test]
    public function navigation_policy_allows_unlisted_groups(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');

        $policy = app(FilamentNavigationPolicy::class);

        $this->assertTrue(
            $policy->canAccessNavigationGroup($user, 'Access Governance'),
            'Groups not listed in filament_navigation_group_permissions remain open.',
        );
    }

    // ── super_admin protection ────────────────────────────────────

    #[Test]
    public function erp_admin_cannot_edit_super_admin_role_permissions(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $this->actingAs($actor);

        $superRole = Role::where('name', 'super_admin')->first();
        $this->assertNotNull($superRole);

        $service = app(RoleGovernanceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/super_admin/');

        $service->syncPermissions($superRole, ['admin.panel.access']);
    }

    #[Test]
    public function super_admin_can_edit_super_admin_role_permissions(): void
    {
        $actor = $this->makeGovernanceUser('super_admin');
        $this->actingAs($actor);

        $superRole = Role::where('name', 'super_admin')->first();

        $service = app(RoleGovernanceService::class);
        $result = $service->syncPermissions($superRole, ['admin.panel.access', 'admin.dashboard.view']);

        $this->assertTrue($result->permissions->pluck('name')->contains('admin.panel.access'));
        $this->assertTrue($result->permissions->pluck('name')->contains('admin.dashboard.view'));
    }

    #[Test]
    public function erp_admin_cannot_delete_super_admin_user(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $this->actingAs($actor);

        $target = $this->makeGovernanceUser('super_admin');

        $service = app(UserGovernanceService::class);

        $this->expectException(\DomainException::class);

        $service->delete($target);
    }

    #[Test]
    public function erp_admin_cannot_update_super_admin_user(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $this->actingAs($actor);

        $target = $this->makeGovernanceUser('super_admin');

        $service = app(UserGovernanceService::class);

        $this->expectException(\DomainException::class);

        $service->update($target, ['is_active' => false]);
    }

    #[Test]
    public function super_admin_can_delete_another_super_admin_user(): void
    {
        $actor = $this->makeGovernanceUser('super_admin');
        $this->actingAs($actor);

        $target = $this->makeGovernanceUser('super_admin');

        $service = app(UserGovernanceService::class);
        $service->delete($target);

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    // ── sales_manager dead reference removal ──────────────────────

    #[Test]
    public function no_sales_manager_role_references_in_policies(): void
    {
        $commissionPolicy = file_get_contents(app_path('Policies/CommissionPolicy.php'));
        $depositPolicy = file_get_contents(app_path('Policies/DepositPolicy.php'));

        $this->assertStringNotContainsString('sales_manager', $commissionPolicy);
        $this->assertStringNotContainsString('sales_manager', $depositPolicy);
    }

    // ── Helpers ───────────────────────────────────────────────────

    protected function makeGovernanceUser(string $governanceRole): User
    {
        $user = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $user->assignRole($governanceRole);

        return $user->fresh();
    }
}
