<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $definitions = config('ai_capabilities.definitions', []);
        foreach ($definitions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Ensure contracts.delete is created if not in config yet (though we just added it)
        Permission::firstOrCreate(['name' => 'contracts.delete', 'guard_name' => 'web']);

        // 2. Create Roles and Assign Permissions
        $allPermissions = array_keys($definitions);

        $roleMap = config('ai_capabilities.bootstrap_role_map', []);

        // Ensure admin has all permissions
        $roleMap['admin'] = $allPermissions;

        foreach ($roleMap as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        // 3. Assign Roles to Existing Users based on their 'type' column
        $users = User::all();
        foreach ($users as $user) {
            // Skip if user already has roles
            if ($user->roles()->count() > 0) {
                continue;
            }

            $userType = $user->type;

            // Special handling for sales leaders
            if ($userType === 'sales' && $user->is_manager) {
                if (Role::where('name', 'sales_leader')->exists()) {
                    $user->assignRole('sales_leader');
                }
                continue;
            }

            // Map user type to role name
            if ($userType === 'user' && isset($roleMap['default'])) {
                $user->assignRole('default');
            } elseif (isset($roleMap[$userType]) && Role::where('name', $userType)->exists()) {
                $user->assignRole($userType);
            } elseif (isset($roleMap['default'])) {
                $user->assignRole('default');
            }
        }

        $this->command->info('✅ Roles and permissions seeded successfully!');
        $this->command->info('📊 Total Permissions: ' . count($allPermissions));
        $this->command->info('👥 Total Roles: ' . count($roleMap));
    }
}
