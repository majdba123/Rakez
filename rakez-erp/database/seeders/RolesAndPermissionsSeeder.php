<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $driver = \DB::getDriverName();

        if ($driver === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        try {
            $definitions = config('ai_capabilities.definitions', []);
            foreach ($definitions as $name => $description) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            $allPermissions = array_keys($definitions);
            $roleMap = config('ai_capabilities.bootstrap_role_map', []);
            $roleMap['admin'] = $allPermissions;

            foreach ($roleMap as $roleName => $permissions) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                $retries = 3;
                while ($retries > 0) {
                    try {
                        \DB::transaction(function () use ($role, $permissions): void {
                            \DB::table('role_has_permissions')
                                ->where('role_id', $role->id)
                                ->delete();

                            $role->syncPermissions($permissions);
                        });

                        break;
                    } catch (\Illuminate\Database\QueryException $exception) {
                        $retries--;

                        if ($retries === 0) {
                            $this->command?->warn("Failed to sync permissions for role: {$roleName}");
                        } else {
                            usleep(50000);
                        }
                    }
                }

                usleep(20000);
            }
        } finally {
            if ($driver === 'mysql') {
                \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        $users = User::all();
        foreach ($users as $user) {
            if ($user->roles()->count() > 0) {
                continue;
            }

            $userType = $user->type;

            if ($userType === 'sales' && $user->is_manager) {
                if (Role::where('name', 'sales_leader')->exists()) {
                    $user->assignRole('sales_leader');
                }

                continue;
            }

            if ($userType === 'user' && isset($roleMap['default'])) {
                $user->assignRole('default');
            } elseif (isset($roleMap[$userType]) && Role::where('name', $userType)->exists()) {
                $user->assignRole($userType);
            } elseif (isset($roleMap['default'])) {
                $user->assignRole('default');
            }
        }

        if ($seededAdmin = User::where('email', 'admin@rakez.com')->first()) {
            if (Role::where('name', 'admin')->exists()) {
                $seededAdmin->syncRoles(['admin']);
            }
        }

        $this->command?->info('Roles and permissions seeded successfully.');
        $this->command?->info('Total permissions: ' . count($allPermissions));
        $this->command?->info('Total roles: ' . count($roleMap));
    }
}
