<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\GovernanceAuditLog;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class AccessGovernanceActionsTest extends BasePermissionTestCase
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
    public function super_admin_can_soft_delete_user_from_filament_users_table_with_audit_logging(): void
    {
        $actor = $this->createGovernanceUser('super_admin');
        $target = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'deletable-user@example.com',
        ]);

        GovernanceAuditLog::query()->delete();

        $this->actingAs($actor);

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('deleteUser', $target->getKey())
            ->callTableAction('deleteUser', $target->getKey())
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.user.deleted',
            'subject_id' => $target->id,
            'actor_id' => $actor->id,
        ]);
    }

    #[Test]
    public function super_admin_can_restore_user_from_filament_users_table_with_audit_logging(): void
    {
        $actor = $this->createGovernanceUser('super_admin');
        $target = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'restorable-user@example.com',
        ]);
        $target->delete();

        GovernanceAuditLog::query()->delete();

        $this->actingAs($actor);

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('restoreUser', $target->getKey())
            ->callTableAction('restoreUser', $target->getKey())
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.user.restored',
            'subject_id' => $target->id,
            'actor_id' => $actor->id,
        ]);
    }

    #[Test]
    public function edit_user_page_delete_action_uses_governance_service_path(): void
    {
        $actor = $this->createGovernanceUser('super_admin');
        $target = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'edit-delete-user@example.com',
        ]);

        GovernanceAuditLog::query()->delete();

        $this->actingAs($actor);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callAction('deleteUser');

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.user.deleted',
            'subject_id' => $target->id,
            'actor_id' => $actor->id,
        ]);
    }

    #[Test]
    public function non_super_admin_cannot_access_filament_user_actions_against_super_admin_targets(): void
    {
        $actor = $this->createAdmin([
            'email' => 'legacy-admin@example.com',
            'is_active' => true,
        ]);
        $superAdmin = $this->createGovernanceUser('super_admin');
        $superAdmin->delete();

        $this->actingAs($actor);

        $this->get('/admin/users')->assertForbidden();

        $record = User::withTrashed()->findOrFail($superAdmin->id);

        $this->assertFalse(UserResource::canDelete($record));
        $this->assertFalse(UserResource::canRestore($record));
    }

    protected function createGovernanceUser(string $role): User
    {
        $user = $this->createDefaultUser([
            'is_active' => true,
            'email' => "{$role}-" . uniqid() . '@example.com',
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
