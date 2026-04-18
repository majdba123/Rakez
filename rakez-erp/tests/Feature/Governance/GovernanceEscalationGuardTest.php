<?php

namespace Tests\Feature\Governance;

use App\Models\GovernanceAuditLog;
use App\Models\User;
use App\Services\Governance\GovernanceCatalog;
use App\Services\Governance\RoleGovernanceService;
use App\Services\Governance\UserGovernanceService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Feature\Auth\BasePermissionTestCase;

class GovernanceEscalationGuardTest extends BasePermissionTestCase
{
    #[Test]
    public function erp_admin_cannot_assign_super_admin_role_via_user_service(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $target = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $target->assignRole('default');

        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);
        $service->update($target, [
            'name' => $target->name,
            'email' => $target->email,
            'type' => $target->type,
            'governance_roles' => ['erp_admin', 'super_admin'],
        ]);

        $target->refresh();
        $this->assertTrue($target->hasRole('erp_admin'));
        $this->assertFalse($target->hasRole('super_admin'), 'erp_admin must not be able to assign super_admin');
    }

    #[Test]
    public function super_admin_can_assign_super_admin_role(): void
    {
        $actor = $this->makeGovernanceUser('super_admin');
        $target = User::factory()->create(['type' => 'default', 'is_active' => true]);
        $target->assignRole('default');

        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);
        $service->update($target, [
            'name' => $target->name,
            'email' => $target->email,
            'type' => $target->type,
            'governance_roles' => ['super_admin'],
        ]);

        $target->refresh();
        $this->assertTrue($target->hasRole('super_admin'));
    }

    #[Test]
    public function erp_admin_cannot_self_escalate_to_super_admin(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');

        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);
        $service->update($actor, [
            'name' => $actor->name,
            'email' => $actor->email,
            'type' => $actor->type,
            'governance_roles' => ['erp_admin', 'super_admin'],
        ]);

        $actor->refresh();
        $this->assertTrue($actor->hasRole('erp_admin'));
        $this->assertFalse($actor->hasRole('super_admin'), 'erp_admin must not self-escalate to super_admin');
    }

    #[Test]
    public function assignable_governance_role_options_hides_super_admin_for_erp_admin(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $catalog = app(GovernanceCatalog::class);

        $options = $catalog->assignableGovernanceRoleOptions($actor);

        $this->assertArrayNotHasKey('super_admin', $options);
        $this->assertArrayHasKey('erp_admin', $options);
    }

    #[Test]
    public function assignable_governance_role_options_shows_super_admin_for_super_admin(): void
    {
        $actor = $this->makeGovernanceUser('super_admin');
        $catalog = app(GovernanceCatalog::class);

        $options = $catalog->assignableGovernanceRoleOptions($actor);

        $this->assertArrayHasKey('super_admin', $options);
        $this->assertArrayHasKey('erp_admin', $options);
        $this->assertSame(__('filament-admin.role_aliases.admin'), $options['super_admin']);
        $this->assertSame(__('filament-admin.role_aliases.legacy_admin'), $options['admin']);
    }

    #[Test]
    public function role_governance_service_refuses_to_edit_operational_role(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('is not editable');

        $operationalRole = Role::findByName('sales', 'web');
        $service = app(RoleGovernanceService::class);

        $service->syncPermissions($operationalRole, ['admin.panel.access']);
    }

    #[Test]
    public function role_governance_service_allows_editing_governance_role(): void
    {
        $governanceRole = Role::findByName('erp_admin', 'web');
        $service = app(RoleGovernanceService::class);

        $result = $service->syncPermissions($governanceRole, [
            'admin.panel.access',
            'admin.dashboard.view',
            'admin.users.view',
        ]);

        $permNames = $result->permissions->pluck('name')->sort()->values()->all();
        $this->assertEquals([
            'admin.dashboard.view',
            'admin.panel.access',
            'admin.users.view',
        ], $permNames);
    }

    #[Test]
    public function role_governance_service_refuses_postponed_temporary_permissions(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $governanceRole = Role::findByName('erp_admin', 'web');
        $service = app(RoleGovernanceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Postponed governance permissions cannot be assigned:');

        $service->syncPermissions($governanceRole, ['admin.temp_permissions.manage']);
    }

    #[Test]
    public function is_editable_role_matches_managed_governance_roles_and_rejects_operational_only_roles(): void
    {
        $catalog = app(GovernanceCatalog::class);

        $this->assertTrue($catalog->isEditableRole('erp_admin'));
        $this->assertTrue($catalog->isEditableRole('super_admin'));
        $this->assertTrue($catalog->isEditableRole('auditor_readonly'));
        $this->assertTrue($catalog->isEditableRole('credit_admin'));
        $this->assertTrue($catalog->isEditableRole('admin'));

        $this->assertFalse($catalog->isEditableRole('sales'));
        $this->assertFalse($catalog->isEditableRole('marketing'));
        $this->assertFalse($catalog->isEditableRole('credit'));
        $this->assertFalse($catalog->isEditableRole('nonexistent_role'));
    }

    #[Test]
    public function user_create_strips_super_admin_for_non_super_admin_actor(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);
        $user = $service->create([
            'name' => 'New Test User',
            'email' => 'newtest@example.com',
            'password' => 'password123',
            'type' => 'default',
            'is_active' => true,
            'governance_roles' => ['erp_admin', 'super_admin'],
        ]);

        $this->assertTrue($user->hasRole('erp_admin'));
        $this->assertFalse($user->hasRole('super_admin'));
    }

    #[Test]
    public function erp_admin_cannot_restore_super_admin_user(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $target = $this->makeGovernanceUser('super_admin');

        $target->delete();

        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only admin can modify or delete another top-level admin user.');

        $service->restore(User::withTrashed()->findOrFail($target->id));
    }

    #[Test]
    public function blocked_super_admin_role_edit_attempt_is_audited(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $this->actingAs($actor);

        $service = app(RoleGovernanceService::class);
        $role = Role::findByName('super_admin', 'web');

        try {
            $service->syncPermissions($role, ['admin.panel.access']);
            $this->fail('Expected non-super_admin actor to be blocked.');
        } catch (\DomainException) {
            // Expected.
        }

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.role.super_admin.protected',
            'actor_id' => $actor->id,
            'subject_id' => $role->id,
        ]);
    }

    #[Test]
    public function blocked_super_admin_user_mutation_attempt_is_audited(): void
    {
        $actor = $this->makeGovernanceUser('erp_admin');
        $target = $this->makeGovernanceUser('super_admin');
        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);

        try {
            $service->update($target, ['is_active' => false]);
            $this->fail('Expected non-super_admin actor to be blocked.');
        } catch (\DomainException) {
            // Expected.
        }

        /** @var GovernanceAuditLog|null $log */
        $log = GovernanceAuditLog::query()
            ->where('event', 'governance.user.super_admin.protected')
            ->where('actor_id', $actor->id)
            ->where('subject_id', $target->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('update', $log->payload['attempted_action'] ?? null);
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
