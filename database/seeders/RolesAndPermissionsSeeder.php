<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        // Disable foreign key checks to prevent deadlocks (MySQL only)
        $driver = \DB::getDriverName();
        if ($driver === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        try {
            // 1. Create Permissions from config definitions
            $definitions = config('ai_capabilities.definitions', []);
            foreach ($definitions as $name => $description) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            // 2. Create Roles and Assign Permissions
            $allPermissions = array_keys($definitions);

            $roleMap = config('ai_capabilities.bootstrap_role_map', []);

            // Ensure admin has all permissions
            $roleMap['admin'] = $allPermissions;

            foreach ($roleMap as $roleName => $permissions) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                // Use DB transaction for each role to avoid deadlocks
                $retries = 3;
                while ($retries > 0) {
                    try {
                        \DB::transaction(function () use ($role, $permissions) {
                            // Delete existing permissions first to avoid conflicts
                            \DB::table('role_has_permissions')
                                ->where('role_id', $role->id)
                                ->delete();

                            // Then sync new permissions
                            $role->syncPermissions($permissions);
                        });
                        break; // Success, exit retry loop
                    } catch (\Illuminate\Database\QueryException $e) {
                        $retries--;
                        if ($retries === 0) {
                            // Last retry failed, log and continue
                            $this->command->warn("Failed to sync permissions for role: {$roleName}");
                        } else {
                            // Wait before retrying
                            usleep(50000); // 50ms
                        }
                    }
                }

                // Small delay to prevent concurrent lock conflicts
                usleep(20000); // 20ms
            }
        } finally {
            // Re-enable foreign key checks (MySQL only)
            if ($driver === 'mysql') {
                \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
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
