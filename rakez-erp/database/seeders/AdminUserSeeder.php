<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = User::withTrashed()->where('email', 'admin@rakez.com')->first();

        if (!$admin) {
            $adminPassword = 'password';

            $admin = User::create([
                'name'       => 'System Administrator',
                'email'      => 'admin@rakez.com',
                'phone'      => '0500000000',
                'password'   => Hash::make($adminPassword),
                'type'       => 'admin',
                'is_manager' => false,
                'is_active'  => true,
            ]);
        }

        // Assign BOTH roles so the admin account is fully operational:
        //   - super_admin → unconditional Filament panel access (GovernanceAccessService bypass)
        //   - admin       → passes 'role:admin' Spatie middleware on all API route groups
        // RolesAndPermissionsSeeder runs after this and preserves both roles.
        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        Role::firstOrCreate(['name' => $superAdminRole, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncRoles([$superAdminRole, 'admin']);
    }
}
