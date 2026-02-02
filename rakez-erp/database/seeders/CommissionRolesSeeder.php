<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CommissionRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create accountant role if it doesn't exist
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);

        // Create permissions for commissions
        $commissionPermissions = [
            'commissions.view',
            'commissions.create',
            'commissions.update',
            'commissions.delete',
            'commissions.approve',
            'commissions.mark_paid',
            'commission_distributions.approve',
            'commission_distributions.reject',
        ];

        foreach ($commissionPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create permissions for deposits
        $depositPermissions = [
            'deposits.view',
            'deposits.create',
            'deposits.update',
            'deposits.delete',
            'deposits.confirm_receipt',
            'deposits.refund',
        ];

        foreach ($depositPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        
        // Admin gets all permissions (handled by Gate::before in AppServiceProvider)
        
        // Sales Manager permissions
        if (Role::where('name', 'sales_manager')->exists()) {
            $salesManager = Role::where('name', 'sales_manager')->first();
            $salesManager->givePermissionTo([
                'commissions.view',
                'commissions.create',
                'commissions.update',
                'commissions.approve',
                'commission_distributions.approve',
                'commission_distributions.reject',
                'deposits.view',
                'deposits.create',
                'deposits.update',
                'deposits.confirm_receipt',
                'deposits.refund',
            ]);
        }

        // Accountant permissions
        $accountantRole->givePermissionTo([
            'commissions.view',
            'commissions.mark_paid',
            'deposits.view',
            'deposits.create',
            'deposits.update',
            'deposits.confirm_receipt',
            'deposits.refund',
        ]);

        // Sales (regular) permissions
        if (Role::where('name', 'sales')->exists()) {
            $sales = Role::where('name', 'sales')->first();
            $sales->givePermissionTo([
                'commissions.view', // Can view their own
                'deposits.view', // Can view their own
                'deposits.create',
            ]);
        }

        $this->command->info('Commission and deposit roles and permissions seeded successfully.');
    }
}
