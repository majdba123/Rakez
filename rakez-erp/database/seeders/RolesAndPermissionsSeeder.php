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
        $roleMap = config('ai_capabilities.bootstrap_role_map', []);
        
        // Add Sales module permissions to admin
        $adminPermissions = $roleMap['admin'] ?? [];
        $salesPermissions = [
            'sales.dashboard.view',
            'sales.projects.view',
            'sales.reservations.create',
            'sales.reservations.view',
            'sales.reservations.confirm',
            'sales.reservations.cancel',
            'sales.targets.view',
            'sales.targets.update',
            'sales.team.manage',
            'sales.attendance.view',
            'sales.attendance.manage',
            'sales.tasks.manage',
        ];
        $roleMap['admin'] = array_unique(array_merge($adminPermissions, $salesPermissions));

        foreach ($roleMap as $roleName => $permissions) {
            // Skip 'default' as it's not a real role usually, but we can create it if needed.
            // For now, let's treat it as a base role or skip it if it's just a fallback.
            // The plan says "Map existing user types", so we'll create roles for all keys.
            
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        // 3. Assign Roles to Existing Users based on their 'type' column
        // This is a migration step to ensure existing users get their roles.
        $users = User::all();
        foreach ($users as $user) {
            $userType = $user->type; // Assuming 'type' column holds values like 'admin', 'project_management'
            
            // Map user type to role name if they match exactly
            if (isset($roleMap[$userType])) {
                $user->assignRole($userType);
            } else {
                // Fallback or log if type doesn't match any role
                // Maybe assign 'default' role if exists and user has no role?
                if (isset($roleMap['default'])) {
                     // $user->assignRole('default'); 
                }
            }
        }
    }
}
