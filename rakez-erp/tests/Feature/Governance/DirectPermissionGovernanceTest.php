<?php

namespace Tests\Feature\Governance;

use App\Models\User;
use App\Services\Governance\DirectPermissionGovernanceService;
use App\Services\Governance\EffectiveAccessSnapshotService;
use App\Services\Governance\UserGovernanceService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class DirectPermissionGovernanceTest extends BasePermissionTestCase
{
    #[Test]
    public function erp_admin_can_access_direct_permissions_pages_while_auditor_cannot_edit(): void
    {
        $target = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $target->assignRole('default');

        $erpAdmin = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $erpAdmin->assignRole('erp_admin');

        $auditor = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $auditor->assignRole('auditor_readonly');

        $this->actingAs($erpAdmin)
            ->get('/admin/direct-permissions')
            ->assertOk();

        $this->actingAs($erpAdmin)
            ->get("/admin/direct-permissions/{$target->id}/edit")
            ->assertOk();

        $this->actingAs($auditor)
            ->get('/admin/direct-permissions')
            ->assertOk();

        $this->actingAs($auditor)
            ->get("/admin/direct-permissions/{$target->id}/edit")
            ->assertForbidden();
    }

    #[Test]
    public function governance_service_can_grant_and_revoke_any_direct_permissions_for_regular_users(): void
    {
        $target = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $target->assignRole('sales');

        $service = app(DirectPermissionGovernanceService::class);

        $service->sync($target, [
            'contracts.view',
            'marketing.reports.view',
            'admin.permissions.view',
        ]);

        $target->refresh();

        $this->assertEqualsCanonicalizing([
            'admin.permissions.view',
            'contracts.view',
            'marketing.reports.view',
        ], $target->permissions()->pluck('name')->all());

        $snapshot = app(EffectiveAccessSnapshotService::class)->forUser($target);

        $this->assertEqualsCanonicalizing([
            'admin.permissions.view',
            'contracts.view',
            'marketing.reports.view',
        ], $snapshot['direct_permissions']);

        $service->revoke($target, 'contracts.view');
        $service->grant($target, 'notifications.manage');

        $target->refresh();

        $this->assertFalse($target->permissions()->pluck('name')->contains('contracts.view'));
        $this->assertTrue($target->permissions()->pluck('name')->contains('notifications.manage'));
        $this->assertTrue($target->permissions()->pluck('name')->contains('marketing.reports.view'));
        $this->assertTrue($target->permissions()->pluck('name')->contains('admin.permissions.view'));
        $this->assertDatabaseCount('governance_audit_logs', 3);
    }

    #[Test]
    public function governance_service_can_grant_and_revoke_direct_permissions_for_super_admin_users(): void
    {
        $actor = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $actor->assignRole('super_admin');

        $target = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $target->assignRole('super_admin');

        $this->actingAs($actor);

        $service = app(DirectPermissionGovernanceService::class);

        $service->grant($target, 'notifications.manage');

        $target->refresh();

        $this->assertTrue($target->permissions()->pluck('name')->contains('notifications.manage'));

        $service->revoke($target, 'notifications.manage');

        $target->refresh();

        $this->assertFalse($target->permissions()->pluck('name')->contains('notifications.manage'));
    }

    #[Test]
    public function erp_admin_cannot_modify_direct_permissions_for_super_admin_user(): void
    {
        $actor = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $actor->assignRole('erp_admin');

        $target = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $target->assignRole('super_admin');

        $this->actingAs($actor);

        $service = app(DirectPermissionGovernanceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only a super_admin can modify direct permissions');

        $service->grant($target, 'notifications.manage');
    }

    #[Test]
    public function postponed_temporary_permissions_cannot_be_assigned_as_direct_permissions(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $target = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $target->assignRole('default');

        $service = app(DirectPermissionGovernanceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Postponed governance permissions cannot be assigned:');

        $service->sync($target, ['admin.temp_permissions.view']);
    }

    #[Test]
    public function direct_permission_resource_refuses_editing_trashed_users(): void
    {
        $actor = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $actor->assignRole('erp_admin');

        $target = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $target->assignRole('default');
        $target->delete();

        $this->actingAs($actor);

        $this->assertFalse(
            \App\Filament\Admin\Resources\DirectPermissions\DirectPermissionResource::canEdit(
                User::withTrashed()->findOrFail($target->id)
            )
        );
    }

    #[Test]
    public function direct_permission_resource_keeps_soft_deleted_users_visible_for_review(): void
    {
        $target = User::factory()->create([
            'type' => 'default',
            'is_active' => true,
        ]);
        $target->assignRole('default');
        $target->givePermissionTo('notifications.view');
        $target->delete();

        $record = \App\Filament\Admin\Resources\DirectPermissions\DirectPermissionResource::getEloquentQuery()
            ->whereKey($target->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertTrue($record->trashed());
    }

    #[Test]
    public function user_governance_service_persists_direct_permissions_on_create_and_update(): void
    {
        $service = app(UserGovernanceService::class);

        $user = $service->create([
            'name' => 'Governance Target',
            'email' => 'governance-target@example.com',
            'phone' => '0512345678',
            'password' => 'secret-password',
            'type' => 'default',
            'is_manager' => false,
            'is_active' => true,
            'governance_roles' => ['erp_admin'],
            'direct_permissions' => ['contracts.view', 'notifications.view'],
        ]);

        $this->assertTrue($user->hasRole('erp_admin'));
        $this->assertEqualsCanonicalizing([
            'contracts.view',
            'notifications.view',
        ], $user->permissions()->pluck('name')->all());

        $updated = $service->update($user, [
            'name' => 'Governance Target Updated',
            'email' => 'governance-target@example.com',
            'phone' => '0512345678',
            'type' => 'default',
            'is_manager' => false,
            'is_active' => true,
            'governance_roles' => ['erp_admin'],
            'direct_permissions' => ['admin.users.view'],
        ]);

        $this->assertEquals('Governance Target Updated', $updated->name);
        $this->assertEqualsCanonicalizing([
            'admin.users.view',
        ], $updated->permissions()->pluck('name')->all());

        $cleared = $service->update($updated, [
            'name' => 'Governance Target Updated',
            'email' => 'governance-target@example.com',
            'phone' => '0512345678',
            'type' => 'default',
            'is_manager' => false,
            'is_active' => true,
            'governance_roles' => ['erp_admin'],
            'direct_permissions' => [],
        ]);

        $this->assertCount(0, $cleared->permissions);
    }
}
