<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\GovernanceTemporaryPermissions\GovernanceTemporaryPermissionResource;
use App\Filament\Admin\Resources\GovernanceTemporaryPermissions\Pages\ListGovernanceTemporaryPermissions;
use App\Models\GovernanceAuditLog;
use App\Models\GovernanceTemporaryPermission;
use App\Models\User;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\GovernanceTemporaryPermissionService;
use Carbon\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class GovernanceTemporaryPermissionTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
        ]);
    }
    #[Test]
    public function config_disable_prevents_temporary_permission_grants(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $subject->assignRole('inventory_admin');

        $actor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $actor->assignRole('erp_admin');

        $service = app(GovernanceTemporaryPermissionService::class);

        $this->assertFalse($service->isEnabled());
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Temporary governance permissions are disabled.');

        $service->grant(
            $subject,
            'credit.dashboard.view',
            $actor,
            Carbon::now()->addDay(),
            'test grant',
        );
    }

    #[Test]
    public function legacy_temporary_permission_rows_are_ignored_by_access_service_when_disabled(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        GovernanceTemporaryPermission::query()->create([
            'user_id' => $subject->id,
            'permission' => 'admin.dashboard.view',
            'granted_by_id' => null,
            'reason' => 'legacy row',
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $access = app(GovernanceAccessService::class);

        $this->assertFalse($access->allows($subject, 'admin.dashboard.view'));
        $this->assertSame(1, GovernanceTemporaryPermission::query()->count());
    }

    #[Test]
    public function rollout_grant_revoke_and_audit_when_enabled(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $subject->assignRole('inventory_admin');

        $actor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $actor->assignRole('erp_admin');

        GovernanceAuditLog::query()->delete();

        $service = app(GovernanceTemporaryPermissionService::class);
        $this->assertTrue($service->isEnabled());

        $row = $service->grant(
            $subject,
            'credit.dashboard.view',
            $actor,
            Carbon::now()->addDay(),
            'breakglass',
        );

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.temp_permission.granted',
            'subject_id' => $row->id,
        ]);

        $access = app(GovernanceAccessService::class);
        $this->assertTrue($access->allows($subject, 'credit.dashboard.view'));

        $service->revoke($row, $actor);

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.temp_permission.revoked',
            'subject_id' => $row->id,
        ]);

        $this->assertNotNull($row->fresh()->revoked_at);
        $this->assertFalse($access->allows($subject->fresh(), 'credit.dashboard.view'));
    }

    #[Test]
    public function filament_temporary_permissions_routes_follow_rollout_and_permissions(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $erp = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $erp->assignRole('erp_admin');

        $auditor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $auditor->assignRole('auditor_readonly');

        $this->actingAs($erp)->get('/admin/governance-temporary-permissions')->assertOk();
        $this->actingAs($erp)->get('/admin/governance-temporary-permissions/create')->assertOk();

        $this->actingAs($auditor)->get('/admin/governance-temporary-permissions')->assertOk();
        $this->actingAs($auditor)->get('/admin/governance-temporary-permissions/create')->assertForbidden();
    }

    #[Test]
    public function resource_static_gates_reflect_rollout_and_mutation_permissions(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $erp = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $erp->assignRole('erp_admin');

        $auditor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $auditor->assignRole('auditor_readonly');

        $this->actingAs($erp);
        $this->assertTrue(GovernanceTemporaryPermissionResource::canViewAny());
        $this->assertTrue(GovernanceTemporaryPermissionResource::canCreate());

        $this->actingAs($auditor);
        $this->assertTrue(GovernanceTemporaryPermissionResource::canViewAny());
        $this->assertFalse(
            GovernanceTemporaryPermissionResource::canCreate(),
            'auditor_readonly must not mutate via canCreate even when view is granted',
        );

        $this->actingAs($erp);
        config(['governance.temporary_permissions.enabled' => false]);
        $this->assertFalse(GovernanceTemporaryPermissionResource::canViewAny());
        $this->assertFalse(GovernanceTemporaryPermissionResource::canCreate());
    }

    #[Test]
    public function workflow_admin_without_temp_permission_cannot_view_resource(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $workflow = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $workflow->assignRole('workflow_admin');

        $this->actingAs($workflow);

        $this->assertFalse(GovernanceTemporaryPermissionResource::canViewAny());
        $this->get('/admin/governance-temporary-permissions')->assertForbidden();
    }

    #[Test]
    public function filament_list_table_revoke_action_respects_manage_permission(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $subject->assignRole('inventory_admin');

        $erp = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $erp->assignRole('erp_admin');

        $auditor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $auditor->assignRole('auditor_readonly');

        $row = GovernanceTemporaryPermission::query()->create([
            'user_id' => $subject->id,
            'permission' => 'admin.dashboard.view',
            'granted_by_id' => $erp->id,
            'reason' => 'test row',
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $this->actingAs($erp);
        Livewire::test(ListGovernanceTemporaryPermissions::class)
            ->assertCanSeeTableRecords([$row])
            ->assertTableActionVisible('revoke', $row->getKey());

        $this->actingAs($auditor);
        Livewire::test(ListGovernanceTemporaryPermissions::class)
            ->assertCanSeeTableRecords([$row])
            ->assertTableActionHidden('revoke', $row->getKey());
    }

    #[Test]
    public function filament_revoke_table_action_persists_via_service_and_writes_audit_log(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $subject->assignRole('inventory_admin');

        $erp = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $erp->assignRole('erp_admin');

        $row = GovernanceTemporaryPermission::query()->create([
            'user_id' => $subject->id,
            'permission' => 'admin.dashboard.view',
            'granted_by_id' => $erp->id,
            'reason' => 'livewire revoke',
            'expires_at' => Carbon::now()->addDay(),
        ]);

        GovernanceAuditLog::query()->delete();

        $this->actingAs($erp);

        Livewire::test(ListGovernanceTemporaryPermissions::class)
            ->callTableAction('revoke', $row->getKey())
            ->assertHasNoTableActionErrors();

        $row->refresh();
        $this->assertNotNull($row->revoked_at);

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.temp_permission.revoked',
            'subject_id' => $row->id,
        ]);
    }

    #[Test]
    public function grant_rejects_unknown_permission_name(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $actor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $actor->assignRole('erp_admin');

        $service = app(GovernanceTemporaryPermissionService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown permission name:');

        $service->grant(
            $subject,
            'fabricated.permission.not_in_dictionary',
            $actor,
            Carbon::now()->addDay(),
            null,
        );
    }

    #[Test]
    public function expire_due_rows_writes_batch_audit_event_when_enabled(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $subject = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $actor = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $actor->assignRole('erp_admin');

        GovernanceTemporaryPermission::query()->create([
            'user_id' => $subject->id,
            'permission' => 'admin.dashboard.view',
            'granted_by_id' => $actor->id,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        GovernanceAuditLog::query()->delete();

        $count = app(GovernanceTemporaryPermissionService::class)->expireDueRows();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.temp_permission.expired_batch',
        ]);
    }

    #[Test]
    public function resource_does_not_expose_edit_or_delete_mutations(): void
    {
        config(['governance.temporary_permissions.enabled' => true]);

        $erp = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $erp->assignRole('erp_admin');

        $row = GovernanceTemporaryPermission::query()->create([
            'user_id' => $erp->id,
            'permission' => 'admin.dashboard.view',
            'granted_by_id' => $erp->id,
            'reason' => 'self row',
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $this->actingAs($erp);
        $this->assertFalse(GovernanceTemporaryPermissionResource::canEdit($row));
        $this->assertFalse(GovernanceTemporaryPermissionResource::canDelete($row));
    }
}
