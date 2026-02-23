<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Test role mapping and synchronization from user types
 */
class RoleMappingTest extends BasePermissionTestCase
{
    #[Test]
    public function user_type_syncs_to_correct_role()
    {
        $types = ['admin', 'sales', 'marketing', 'project_management', 'hr', 'editor', 'developer'];
        
        foreach ($types as $type) {
            $user = User::factory()->create(['type' => $type, 'is_manager' => false]);
            $user->syncRolesFromType();
            
            $this->assertTrue(
                $user->hasRole($type),
                "User with type '{$type}' should have role '{$type}'"
            );
        }
    }

    #[Test]
    public function sales_manager_syncs_to_sales_leader_role()
    {
        $user = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $user->syncRolesFromType();
        
        $this->assertTrue($user->hasRole('sales_leader'));
        $this->assertFalse($user->hasRole('sales'));
    }

    #[Test]
    public function sales_staff_syncs_to_sales_role()
    {
        $user = User::factory()->create(['type' => 'sales', 'is_manager' => false]);
        $user->syncRolesFromType();
        
        $this->assertTrue($user->hasRole('sales'));
        $this->assertFalse($user->hasRole('sales_leader'));
    }

    #[Test]
    public function admin_user_has_all_permissions()
    {
        $admin = $this->createAdmin();
        
        $allPermissions = array_keys(config('ai_capabilities.definitions', []));
        
        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $admin->hasPermissionTo($permission),
                "Admin should have permission: {$permission}"
            );
        }
    }

    #[Test]
    public function sales_role_has_correct_permissions()
    {
        $expectedPermissions = [
            'sales.dashboard.view',
            'sales.projects.view',
            'sales.units.view',
            'sales.units.book',
            'sales.reservations.create',
            'sales.reservations.view',
            'sales.reservations.confirm',
            'sales.reservations.cancel',
            'sales.waiting_list.create',
            'sales.goals.view',
            'sales.schedule.view',
            'sales.targets.view',
            'sales.targets.update',
            'sales.attendance.view',
            'notifications.view',
            'exclusive_projects.request',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];

        $this->assertRoleHasPermissions('sales', $expectedPermissions);
    }

    #[Test]
    public function sales_leader_role_has_additional_permissions()
    {
        $leaderOnlyPermissions = [
            'sales.waiting_list.convert',
            'sales.goals.create',
            'sales.team.manage',
            'sales.attendance.manage',
            'sales.tasks.manage',
            'sales.tasks.create_for_marketing',
            'sales.projects.allocate_shifts',
        ];

        $this->assertRoleHasPermissions('sales_leader', $leaderOnlyPermissions);
        
        // Should also have all base sales permissions
        $this->assertRoleHasPermissions('sales_leader', [
            'sales.dashboard.view',
            'sales.projects.view',
            'sales.reservations.create',
        ]);
    }

    #[Test]
    public function marketing_role_has_correct_permissions()
    {
        $expectedPermissions = [
            'marketing.dashboard.view',
            'marketing.projects.view',
            'marketing.plans.create',
            'marketing.budgets.manage',
            'marketing.tasks.view',
            'marketing.tasks.confirm',
            'marketing.reports.view',
            'notifications.view',
            'exclusive_projects.request',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];

        $this->assertRoleHasPermissions('marketing', $expectedPermissions);
    }

    #[Test]
    public function project_management_role_has_correct_permissions()
    {
        $expectedPermissions = [
            'contracts.view',
            'contracts.view_all',
            'contracts.approve',
            'units.view',
            'units.edit',
            'units.csv_upload',
            'second_party.view',
            'second_party.edit',
            'departments.boards.view',
            'departments.boards.edit',
            'departments.photography.view',
            'departments.photography.edit',
            'dashboard.analytics.view',
            'notifications.view',
            'projects.view',
            'projects.create',
            'projects.media.upload',
            'projects.team.create',
            'projects.team.assign_leader',
            'projects.team.allocate',
            'exclusive_projects.request',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];

        $this->assertRoleHasPermissions('project_management', $expectedPermissions);
    }

    #[Test]
    public function project_management_manager_has_dynamic_permissions()
    {
        $manager = $this->createProjectManagementManager();
        
        // Should have base PM permissions
        $this->assertTrue($manager->hasPermissionTo('projects.view'));
        $this->assertTrue($manager->hasPermissionTo('contracts.view'));
        
        // Should have dynamic manager permissions
        $this->assertTrue($manager->isProjectManagementManager());
        $this->assertUserHasEffectivePermission($manager, 'projects.approve');
        $this->assertUserHasEffectivePermission($manager, 'projects.media.approve');
        $this->assertUserHasEffectivePermission($manager, 'exclusive_projects.approve');
        $this->assertUserHasEffectivePermission($manager, 'projects.archive');
    }

    #[Test]
    public function project_management_staff_does_not_have_manager_permissions()
    {
        $staff = $this->createProjectManagementStaff();
        
        // Should have base PM permissions
        $this->assertTrue($staff->hasPermissionTo('projects.view'));
        $this->assertTrue($staff->hasPermissionTo('contracts.view'));
        
        // Should NOT have manager permissions
        $this->assertFalse($staff->isProjectManagementManager());
        $this->assertUserDoesNotHaveEffectivePermission($staff, 'projects.approve');
        $this->assertUserDoesNotHaveEffectivePermission($staff, 'projects.media.approve');
        $this->assertUserDoesNotHaveEffectivePermission($staff, 'exclusive_projects.approve');
    }

    #[Test]
    public function hr_role_has_correct_permissions()
    {
        $expectedPermissions = [
            'hr.employees.manage',
            'hr.users.create',
            'hr.performance.view',
            'hr.reports.print',
            'notifications.view',
        ];

        $this->assertRoleHasPermissions('hr', $expectedPermissions);
    }

    #[Test]
    public function hr_role_does_not_have_exclusive_project_permissions()
    {
        $forbiddenPermissions = [
            'exclusive_projects.request',
            'exclusive_projects.approve',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];

        $this->assertRoleDoesNotHavePermissions('hr', $forbiddenPermissions);
    }

    #[Test]
    public function editor_role_has_correct_permissions()
    {
        $expectedPermissions = [
            'contracts.view',
            'contracts.view_all',
            'departments.montage.view',
            'departments.montage.edit',
            'notifications.view',
            'editing.projects.view',
            'editing.media.upload',
            'exclusive_projects.request',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];

        $this->assertRoleHasPermissions('editor', $expectedPermissions);
    }

    #[Test]
    public function developer_role_has_correct_permissions()
    {
        $expectedPermissions = [
            'contracts.view',
            'contracts.create',
            'notifications.view',
            'exclusive_projects.request',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];

        $this->assertRoleHasPermissions('developer', $expectedPermissions);
    }

    #[Test]
    public function default_role_has_minimal_permissions()
    {
        $expectedPermissions = [
            'contracts.view',
            'notifications.view',
        ];

        $this->assertRoleHasPermissions('default', $expectedPermissions);
    }

    #[Test]
    public function is_sales_leader_method_works_correctly()
    {
        $salesStaff = $this->createSalesStaff();
        $salesLeader = $this->createSalesLeader();
        $admin = $this->createAdmin();
        
        $this->assertFalse($salesStaff->isSalesLeader());
        $this->assertTrue($salesLeader->isSalesLeader());
        $this->assertFalse($admin->isSalesLeader());
    }

    #[Test]
    public function is_project_management_manager_method_works_correctly()
    {
        $pmStaff = $this->createProjectManagementStaff();
        $pmManager = $this->createProjectManagementManager();
        $admin = $this->createAdmin();
        
        $this->assertFalse($pmStaff->isProjectManagementManager());
        $this->assertTrue($pmManager->isProjectManagementManager());
        $this->assertFalse($admin->isProjectManagementManager());
    }

    #[Test]
    public function user_type_field_matches_assigned_role()
    {
        $users = $this->createAllUserTypes();
        
        foreach ($users as $type => $user) {
            $expectedRole = $type === 'sales_leader' ? 'sales_leader' : 
                           ($type === 'project_management_manager' ? 'project_management' : $type);
            
            $this->assertTrue(
                $user->hasRole($expectedRole),
                "User of type '{$type}' should have role '{$expectedRole}'"
            );
        }
    }

    #[Test]
    public function role_sync_does_not_duplicate_roles()
    {
        $user = User::factory()->create(['type' => 'sales', 'is_manager' => false]);
        
        // Sync multiple times
        $user->syncRolesFromType();
        $user->syncRolesFromType();
        $user->syncRolesFromType();
        
        // Should only have one role
        $this->assertEquals(1, $user->roles()->count());
        $this->assertTrue($user->hasRole('sales'));
    }

    #[Test]
    public function changing_user_type_updates_role()
    {
        $user = User::factory()->create(['type' => 'sales', 'is_manager' => false]);
        $user->syncRolesFromType();
        
        $this->assertTrue($user->hasRole('sales'));
        
        // Change type
        $user->type = 'marketing';
        $user->save();
        $user->syncRolesFromType();
        
        $this->assertFalse($user->hasRole('sales'));
        $this->assertTrue($user->hasRole('marketing'));
    }

    #[Test]
    public function promoting_sales_staff_to_leader_updates_role()
    {
        $user = User::factory()->create(['type' => 'sales', 'is_manager' => false]);
        $user->syncRolesFromType();
        
        $this->assertTrue($user->hasRole('sales'));
        $this->assertFalse($user->hasRole('sales_leader'));
        
        // Promote to leader
        $user->is_manager = true;
        $user->save();
        $user->syncRolesFromType();
        
        $this->assertFalse($user->hasRole('sales'));
        $this->assertTrue($user->hasRole('sales_leader'));
    }

    #[Test]
    public function all_roles_exist_in_database()
    {
        $expectedRoles = [
            'admin',
            'sales',
            'sales_leader',
            'marketing',
            'project_management',
            'hr',
            'editor',
            'developer',
            'default',
        ];

        foreach ($expectedRoles as $roleName) {
            $this->assertNotNull(
                Role::findByName($roleName, 'web'),
                "Role '{$roleName}' should exist in database"
            );
        }
    }

    #[Test]
    public function all_permissions_exist_in_database()
    {
        $allPermissions = array_keys(config('ai_capabilities.definitions', []));
        
        foreach ($allPermissions as $permissionName) {
            $this->assertNotNull(
                \Spatie\Permission\Models\Permission::findByName($permissionName, 'web'),
                "Permission '{$permissionName}' should exist in database"
            );
        }
    }
}
