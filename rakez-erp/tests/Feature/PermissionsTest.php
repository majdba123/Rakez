<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function project_management_staff_has_correct_permissions()
    {
        $user = User::factory()->create(['type' => 'project_management', 'is_manager' => false]);
        $user->syncRolesFromType();

        $role = Role::findByName('project_management', 'web');
        $permissions = [
            'projects.view',
            'projects.create',
            'projects.media.upload',
            'projects.team.create',
            'exclusive_projects.request',
        ];

        foreach ($permissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission));
            $this->assertTrue($user->hasPermissionTo($permission));
        }

        // Should NOT have manager permissions
        $this->assertFalse($user->isProjectManagementManager());
    }

    /** @test */
    public function project_management_manager_has_additional_permissions()
    {
        $basePermissions = [
            'projects.view',
            'projects.create',
            'exclusive_projects.request',
        ];

        $manager = User::factory()->create([
            'type' => 'project_management',
            'is_manager' => true,
        ]);
        $manager->syncRolesFromType();

        // Manager should have base permissions
        $role = Role::findByName('project_management', 'web');
        foreach ($basePermissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission));
            $this->assertTrue($manager->hasPermissionTo($permission));
        }

        // Manager should be identified correctly
        $this->assertTrue($manager->isProjectManagementManager());

        // Manager should have effective permissions including dynamic ones
        $effectivePermissions = $manager->getEffectivePermissions();
        $this->assertContains('projects.approve', $effectivePermissions);
        $this->assertContains('projects.media.approve', $effectivePermissions);
        $this->assertContains('exclusive_projects.approve', $effectivePermissions);
    }

    /** @test */
    public function sales_staff_has_correct_permissions()
    {
        $user = User::factory()->create(['type' => 'sales', 'is_manager' => false]);
        $user->syncRolesFromType();

        $role = Role::findByName('sales', 'web');
        $permissions = [
            'sales.projects.view',
            'sales.units.view',
            'sales.units.book',
            'sales.waiting_list.create',
            'sales.goals.view',
            'exclusive_projects.request',
        ];

        foreach ($permissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission));
            $this->assertTrue($user->hasPermissionTo($permission));
        }

        // Should NOT have leader permissions
        $this->assertFalse($user->isSalesLeader());
    }

    /** @test */
    public function sales_leader_has_additional_permissions()
    {
        $leader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $leader->syncRolesFromType();

        $role = Role::findByName('sales_leader', 'web');
        $permissions = [
            'sales.projects.view',
            'sales.waiting_list.create',
            'sales.waiting_list.convert',
            'sales.goals.create',
            'sales.team.manage',
            'exclusive_projects.request',
        ];

        foreach ($permissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission));
            $this->assertTrue($leader->hasPermissionTo($permission));
        }

        $this->assertTrue($leader->isSalesLeader());
    }

    /** @test */
    public function hr_staff_does_not_have_exclusive_project_permissions()
    {
        $user = User::factory()->create(['type' => 'hr']);
        $user->syncRolesFromType();

        $role = Role::findByName('hr', 'web');
        $hrPermissions = [
            'hr.employees.manage',
            'hr.users.create',
            'hr.performance.view',
        ];

        // Should have HR permissions
        foreach ($hrPermissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission));
            $this->assertTrue($user->hasPermissionTo($permission));
        }

        // Should NOT have exclusive project permissions
        $this->assertFalse($user->hasPermissionTo('exclusive_projects.request'));
    }

    /** @test */
    public function editing_staff_has_correct_permissions()
    {
        $user = User::factory()->create(['type' => 'editor']);
        $user->syncRolesFromType();

        $role = Role::findByName('editor', 'web');
        $permissions = [
            'editing.projects.view',
            'editing.media.upload',
            'exclusive_projects.request',
        ];

        foreach ($permissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission));
            $this->assertTrue($user->hasPermissionTo($permission));
        }
    }

    /** @test */
    public function marketing_staff_has_correct_permissions()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRolesFromType();

        $role = Role::findByName('marketing', 'web');
        $permissions = [
            'marketing.projects.view',
            'marketing.plans.create',
            'marketing.budgets.manage',
            'marketing.tasks.confirm',
            'exclusive_projects.request',
        ];

        foreach ($permissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission));
            $this->assertTrue($user->hasPermissionTo($permission));
        }
    }
}
