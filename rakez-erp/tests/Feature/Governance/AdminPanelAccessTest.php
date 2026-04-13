<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\EffectiveAccess\EffectiveAccessResource;
use App\Models\User;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\UserGovernanceService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class AdminPanelAccessTest extends BasePermissionTestCase
{
    #[Test]
    public function guest_can_view_admin_login_page(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    #[Test]
    public function admin_role_with_panel_permission_can_access_admin_panel(): void
    {
        $admin = $this->createAdmin();

        $this->assertTrue(app(GovernanceAccessService::class)->canAccessPanel($admin));
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    #[Test]
    public function direct_panel_permission_without_governance_role_still_does_not_grant_panel_access(): void
    {
        $user = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $user->assignRole('default');
        $user->givePermissionTo('admin.panel.access');

        $this->assertFalse(app(GovernanceAccessService::class)->canAccessPanel($user));
    }

    #[Test]
    public function erp_admin_with_panel_permission_can_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $user->assignRole('erp_admin');

        $this->assertTrue(app(GovernanceAccessService::class)->canAccessPanel($user));
        $this->actingAs($user)->get('/admin')->assertOk();
    }

    #[Test]
    public function effective_access_panel_eligible_filter_matches_role_inherited_panel_access(): void
    {
        $eligible = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $eligible->assignRole('erp_admin');

        $ineligible = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $ineligible->assignRole('default');

        $this->assertTrue(app(GovernanceAccessService::class)->canAccessPanel($eligible));
        $this->assertFalse(app(GovernanceAccessService::class)->canAccessPanel($ineligible));

        $eligibleIds = EffectiveAccessResource::applyPanelEligibleFilter(User::query())
            ->pluck('id')
            ->all();
        $ineligibleIds = EffectiveAccessResource::applyPanelIneligibleFilter(User::query())
            ->pluck('id')
            ->all();

        $this->assertContains($eligible->id, $eligibleIds);
        $this->assertNotContains($eligible->id, $ineligibleIds);
        $this->assertContains($ineligible->id, $ineligibleIds);
    }

    #[Test]
    public function inactive_governance_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'type' => 'default',
            'is_active' => false,
        ]);
        $user->assignRole('erp_admin');

        $this->assertFalse(app(GovernanceAccessService::class)->canAccessPanel($user));
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function sync_roles_from_type_preserves_governance_overlay_roles(): void
    {
        $user = User::factory()->create([
            'type' => 'marketing',
            'is_manager' => false,
        ]);
        $user->assignRole('erp_admin');

        $user->type = 'sales';
        $user->is_manager = false;
        $user->syncRolesFromType();

        $this->assertTrue($user->hasRole('erp_admin'));
        $this->assertTrue($user->hasRole('sales'));
        $this->assertFalse($user->hasRole('marketing'));
    }

    #[Test]
    public function governance_user_service_soft_deletes_and_restores_users(): void
    {
        $user = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);

        $service = app(UserGovernanceService::class);

        $service->delete($user);
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $restored = $service->restore(User::withTrashed()->findOrFail($user->id));

        $this->assertNotNull($restored);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('governance_audit_logs', 2);
    }
}
