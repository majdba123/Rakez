<?php

namespace Tests\Traits;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Build users matching {@see config('ai_capabilities.bootstrap_role_map')} (same idea as RolesAndPermissionsSeeder).
 */
trait CreatesUsersWithBootstrapRole
{
    protected function createUserWithBootstrapRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        if ($roleName === 'admin') {
            $permissions = array_keys(config('ai_capabilities.definitions', []));
        } else {
            $permissions = config('ai_capabilities.bootstrap_role_map.'.$roleName, []);
        }

        if ($permissions === []) {
            $this->fail("Unknown or empty bootstrap role: {$roleName}");
        }

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        $role->syncPermissions($permissions);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }
}
