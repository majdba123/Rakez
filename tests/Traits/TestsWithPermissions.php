<?php

namespace Tests\Traits;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Trait for tests that require permission setup
 * 
 * Provides reusable methods for creating and managing permissions and roles in tests
 */
trait TestsWithPermissions
{
    /**
     * Ensure a permission exists
     *
     * @param string $permission Permission name
     * @param string $guardName Guard name (default: 'web')
     * @return Permission
     */
    protected function ensurePermission(string $permission, string $guardName = 'web'): Permission
    {
        return Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => $guardName,
        ]);
    }

    /**
     * Ensure multiple permissions exist
     *
     * @param array $permissions Array of permission names
     * @param string $guardName Guard name (default: 'web')
     * @return array Array of Permission objects
     */
    protected function ensurePermissions(array $permissions, string $guardName = 'web'): array
    {
        return array_map(
            fn($permission) => $this->ensurePermission($permission, $guardName),
            $permissions
        );
    }

    /**
     * Grant permission to a user
     *
     * @param User $user User to grant permission to
     * @param string $permission Permission name
     * @return void
     */
    protected function grantPermission(User $user, string $permission): void
    {
        $this->ensurePermission($permission);
        $user->givePermissionTo($permission);
    }

    /**
     * Grant multiple permissions to a user
     *
     * @param User $user User to grant permissions to
     * @param array $permissions Array of permission names
     * @return void
     */
    protected function grantPermissions(User $user, array $permissions): void
    {
        $this->ensurePermissions($permissions);
        $user->givePermissionTo($permissions);
    }

    /**
     * Create a user with specific permissions
     *
     * @param array $permissions Array of permission names
     * @param array $attributes Additional user attributes
     * @return User
     */
    protected function createUserWithPermissions(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->grantPermissions($user, $permissions);
        return $user;
    }

    /**
     * Grant AI section permissions to a user
     *
     * @param User $user User to grant permissions to
     * @param string $section Section name (e.g., 'contracts', 'units')
     * @return void
     */
    protected function grantAISectionPermission(User $user, string $section): void
    {
        $sectionPermissions = [
            'contracts' => ['contracts.view'],
            'units' => ['units.view'],
            'units_csv' => ['units.csv_upload'],
            'second_party' => ['second_party.view'],
            'departments_boards' => ['departments.boards.view'],
            'departments_photography' => ['departments.photography.view'],
            'departments_montage' => ['departments.montage.view'],
            'notifications' => ['notifications.view'],
            'dashboard' => ['dashboard.analytics.view'],
            'marketing_dashboard' => ['marketing.dashboard.view'],
            'marketing_projects' => ['marketing.projects.view'],
            'marketing_tasks' => ['marketing.tasks.view'],
        ];

        $permissions = $sectionPermissions[$section] ?? [];
        if (!empty($permissions)) {
            $this->grantPermissions($user, $permissions);
        }
    }

    /**
     * Grant multiple AI section permissions to a user
     *
     * @param User $user User to grant permissions to
     * @param array $sections Array of section names
     * @return void
     */
    protected function grantAISectionPermissions(User $user, array $sections): void
    {
        foreach ($sections as $section) {
            $this->grantAISectionPermission($user, $section);
        }
    }

    /**
     * Create a user with AI section permissions
     *
     * @param array $sections Array of section names
     * @param array $attributes Additional user attributes
     * @return User
     */
    protected function createUserWithAISections(array $sections, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->grantAISectionPermissions($user, $sections);
        return $user;
    }

    /**
     * Create an admin user with all permissions
     *
     * @param array $attributes Additional user attributes
     * @return User
     */
    protected function createAdminUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['type' => 'admin'], $attributes));
        
        // Grant all permissions from config
        $definitions = config('ai_capabilities.definitions', []);
        $permissions = array_keys($definitions);
        
        if (!empty($permissions)) {
            $this->grantPermissions($user, $permissions);
        }
        
        return $user;
    }

    /**
     * Create a role with permissions
     *
     * @param string $roleName Role name
     * @param array $permissions Array of permission names
     * @param string $guardName Guard name (default: 'web')
     * @return Role
     */
    protected function createRoleWithPermissions(
        string $roleName,
        array $permissions,
        string $guardName = 'web'
    ): Role {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => $guardName,
        ]);

        $this->ensurePermissions($permissions, $guardName);
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Assign a role to a user
     *
     * @param User $user User to assign role to
     * @param string $roleName Role name
     * @return void
     */
    protected function assignRole(User $user, string $roleName): void
    {
        $user->assignRole($roleName);
    }

    /**
     * Create a user with a specific role
     *
     * @param string $roleName Role name
     * @param array $attributes Additional user attributes
     * @return User
     */
    protected function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->assignRole($user, $roleName);
        return $user;
    }

    /**
     * Revoke permission from a user
     *
     * @param User $user User to revoke permission from
     * @param string $permission Permission name
     * @return void
     */
    protected function revokePermission(User $user, string $permission): void
    {
        $user->revokePermissionTo($permission);
    }

    /**
     * Revoke multiple permissions from a user
     *
     * @param User $user User to revoke permissions from
     * @param array $permissions Array of permission names
     * @return void
     */
    protected function revokePermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->revokePermission($user, $permission);
        }
    }

    /**
     * Assert user has permission
     *
     * @param User $user User to check
     * @param string $permission Permission name
     * @return void
     */
    protected function assertUserHasPermission(User $user, string $permission): void
    {
        $this->assertTrue(
            $user->hasPermissionTo($permission),
            "User does not have permission: {$permission}"
        );
    }

    /**
     * Assert user does not have permission
     *
     * @param User $user User to check
     * @param string $permission Permission name
     * @return void
     */
    protected function assertUserDoesNotHavePermission(User $user, string $permission): void
    {
        $this->assertFalse(
            $user->hasPermissionTo($permission),
            "User has permission: {$permission}"
        );
    }

    /**
     * Assert user has role
     *
     * @param User $user User to check
     * @param string $roleName Role name
     * @return void
     */
    protected function assertUserHasRole(User $user, string $roleName): void
    {
        $this->assertTrue(
            $user->hasRole($roleName),
            "User does not have role: {$roleName}"
        );
    }

    /**
     * Setup common AI permissions for tests
     *
     * @return void
     */
    protected function setupCommonAIPermissions(): void
    {
        $commonPermissions = [
            'contracts.view',
            'contracts.create',
            'units.view',
            'units.edit',
            'marketing.dashboard.view',
            'marketing.projects.view',
            'marketing.tasks.view',
            'sales.dashboard.view',
            'sales.projects.view',
            'dashboard.analytics.view',
            'notifications.view',
        ];

        $this->ensurePermissions($commonPermissions);
    }
}
