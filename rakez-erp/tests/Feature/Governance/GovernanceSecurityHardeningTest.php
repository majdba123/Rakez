<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\DirectPermissions\DirectPermissionResource;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\GovernanceAuditLog;
use App\Models\GovernanceTemporaryPermission;
use App\Models\User;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\GovernanceCatalog;
use App\Services\Governance\GovernanceTemporaryPermissionService;
use App\Services\Governance\RoleGovernanceService;
use App\Services\Governance\UserGovernanceService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Feature\Auth\BasePermissionTestCase;

class GovernanceSecurityHardeningTest extends BasePermissionTestCase
{
    #[Test]
    public function every_governance_mutation_leaves_an_audit_trail(): void
    {
        $actor = $this->makeGovernanceUser('super_admin');
        $this->actingAs($actor);

        GovernanceAuditLog::query()->delete();

        $service = app(UserGovernanceService::class);

        $user = $service->create([
            'name' => 'Audit Trail Test',
            'email' => 'audit-trail@example.com',
            'password' => 'password123',
            'type' => 'default',
            'is_active' => true,
            'governance_roles' => ['erp_admin'],
        ]);

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.user.created',
            'subject_id' => $user->id,
        ]);

        $service->update($user, [
            'name' => 'Updated Name',
            'email' => $user->email,
            'type' => $user->type,
            'governance_roles' => ['erp_admin'],
        ]);

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.user.updated',
            'subject_id' => $user->id,
        ]);

        $service->delete($user);

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.user.deleted',
            'subject_id' => $user->id,
        ]);

        $this->assertGreaterThanOrEqual(3, GovernanceAuditLog::count());
    }

    #[Test]
    public function role_permission_sync_leaves_before_after_audit_trail(): void
    {
        $actor = $this->makeGovernanceUser('super_admin');
        $this->actingAs($actor);

        GovernanceAuditLog::query()->delete();

        $role = Role::findByName('erp_admin', 'web');
        $beforePerms = $role->permissions()->pluck('name')->sort()->values()->all();

        app(RoleGovernanceService::class)->syncPermissions($role, [
            'admin.panel.access',
            'admin.dashboard.view',
        ]);

        $log = GovernanceAuditLog::where('event', 'governance.role.permissions_synced')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals($beforePerms, $log->payload['before']);
        $this->assertEquals(['admin.dashboard.view', 'admin.panel.access'], $log->payload['after']);

        app(RoleGovernanceService::class)->syncPermissions($role, $beforePerms);
    }

    #[Test]
    public function temporary_permission_service_respects_disabled_config(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $actor = $this->makeGovernanceUser('erp_admin');
        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $subject->assignRole('auditor_readonly');

        GovernanceAuditLog::query()->delete();

        $service = app(GovernanceTemporaryPermissionService::class);
        $this->assertFalse($service->isEnabled());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Temporary governance permissions are disabled.');

        $service->grant($subject, 'credit.dashboard.view', $actor, Carbon::now()->addHour(), 'test');
    }

    #[Test]
    public function temporary_permission_rows_do_not_grant_access_when_feature_is_disabled(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $subject->assignRole('auditor_readonly');

        GovernanceTemporaryPermission::query()->create([
            'user_id' => $subject->id,
            'permission' => 'credit.dashboard.view',
            'granted_by_id' => null,
            'reason' => 'legacy row',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $access = app(GovernanceAccessService::class);
        $this->assertFalse($access->allows($subject, 'credit.dashboard.view'));

        $snapshot = app(\App\Services\Governance\EffectiveAccessSnapshotService::class)->forUser($subject);
        $this->assertSame([], $snapshot['temporary_permissions']);
    }

    #[Test]
    public function expire_due_rows_returns_zero_when_temporary_permissions_are_disabled(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $service = app(GovernanceTemporaryPermissionService::class);
        $this->assertSame(0, $service->expireDueRows());
    }

    #[Test]
    public function inactive_user_cannot_access_panel_even_with_governance_role(): void
    {
        $user = User::factory()->create(['type' => 'default', 'is_active' => false]);
        $user->assignRole('super_admin');

        $access = app(GovernanceAccessService::class);
        $this->assertFalse($access->canAccessPanel($user));
    }

    #[Test]
    public function catalog_only_recognizes_dictionary_permissions(): void
    {
        $catalog = app(GovernanceCatalog::class);

        $this->assertTrue($catalog->isKnownPermission('admin.panel.access'));
        $this->assertFalse($catalog->isKnownPermission('fabricated.permission.xyz'));
    }

    #[Test]
    public function governance_access_service_rejects_unknown_permissions_and_non_panel_users(): void
    {
        $user = $this->makeGovernanceUser('erp_admin');
        $access = app(GovernanceAccessService::class);

        $this->assertFalse($access->allows($user, 'admin.panel.access'));
        $this->assertFalse($access->allows($user, 'admin.dashboard.view'));
        $this->assertFalse($access->allows($user, 'admin.temp_permissions.view'));

        $this->assertFalse(
            $access->allows($user, 'fabricated.permission.xyz'),
            'Unknown permissions must be rejected even for governance users'
        );
    }

    #[Test]
    public function super_admin_bypass_only_applies_to_active_panel_permissions(): void
    {
        $user = $this->makeGovernanceUser('super_admin');
        $access = app(GovernanceAccessService::class);

        $this->assertTrue($access->allows($user, 'admin.panel.access'));
        $this->assertTrue($access->allows($user, 'credit.dashboard.view'));
        $this->assertTrue($access->allows($user, 'admin.temp_permissions.manage'));

        $this->assertFalse($access->allows($user, 'fabricated.permission.not_in_dictionary'));
    }

    #[Test]
    public function force_delete_is_disabled_across_governance_resources(): void
    {
        $user = User::factory()->create(['type' => 'default', 'is_active' => true]);

        $this->assertFalse(
            \App\Filament\Admin\Resources\Users\UserResource::canForceDelete($user),
            'UserResource must not allow force delete'
        );
    }

    #[Test]
    public function auditor_readonly_cannot_use_manage_gates_even_when_permissions_are_directly_granted(): void
    {
        $auditor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $auditor->assignRole('auditor_readonly');
        $auditor->syncPermissions([
            'admin.users.manage',
            'admin.roles.manage',
            'admin.direct_permissions.manage',
            'accounting.deposits.manage',
            'accounting.commissions.approve',
            'commissions.mark_paid',
            'accounting.salaries.distribute',
        ]);

        $this->actingAs($auditor);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $subject->assignRole('erp_admin');

        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canEdit($subject));

        $role = Role::findByName('erp_admin', 'web');
        $this->assertNotNull($role);
        $this->assertFalse(RoleResource::canEdit($role));

        $this->assertFalse(DirectPermissionResource::canEdit($subject));
    }

    #[Test]
    public function temporary_permission_revoke_is_blocked_when_feature_is_disabled(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $actor = $this->makeGovernanceUser('erp_admin');
        $row = GovernanceTemporaryPermission::query()->create([
            'user_id' => User::factory()->create(['type' => 'default', 'is_active' => true])->id,
            'permission' => 'admin.dashboard.view',
            'granted_by_id' => null,
            'reason' => 'legacy row',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $service = app(GovernanceTemporaryPermissionService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Temporary governance permissions are disabled.');

        $service->revoke($row, $actor);
    }

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
