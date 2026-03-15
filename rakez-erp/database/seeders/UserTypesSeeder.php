<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserTypesSeeder extends Seeder
{
    /**
     * Create one user per type and assign the corresponding role/permissions.
     * Run after RolesAndPermissionsSeeder.
     */
    public function run(): void
    {
        $types = config('user_types.all', []);
        $labels = $this->getTypeLabels();

        foreach ($types as $type) {
            $email = $this->emailForType($type);
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $labels[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                    'email' => $email,
                    'phone' => $this->phoneForType($type),
                    'password' => Hash::make('password'),
                    'type' => $type,
                    'is_manager' => $type === 'sales_leader',
                    'is_active' => true,
                ]);
                $this->command->info("Created user: {$email} ({$type})");
            }

            $this->assignRole($user, $type);
        }

        $this->command->info('✅ User types seeded successfully!');
    }

    private function emailForType(string $type): string
    {
        return "{$type}@rakez.com";
    }

    private function phoneForType(string $type): string
    {
        $phones = [
            'admin' => '0500000001',
            'project_management' => '0500000002',
            'editor' => '0500000003',
            'developer' => '0500000004',
            'marketing' => '0500000005',
            'sales' => '0500000006',
            'sales_leader' => '0500000007',
            'hr' => '0500000008',
            'credit' => '0500000009',
            'accounting' => '0500000010',
            'inventory' => '0500000011',
            'default' => '0500000012',
            'accountant' => '0500000013',
        ];
        return $phones[$type] ?? '0500000000';
    }

    private function getTypeLabels(): array
    {
        return [
            'admin' => 'مدير النظام',
            'project_management' => 'إدارة المشاريع',
            'editor' => 'المحرر',
            'developer' => 'المطور',
            'marketing' => 'التسويق',
            'sales' => 'المبيعات',
            'sales_leader' => 'قائد المبيعات',
            'hr' => 'الموارد البشرية',
            'credit' => 'الائتمان',
            'accounting' => 'المحاسبة',
            'inventory' => 'المخزون',
            'default' => 'مستخدم افتراضي',
            'accountant' => 'المحاسب',
        ];
    }

    private function assignRole(User $user, string $type): void
    {
        $roleName = $this->roleForType($type);
        if (!Role::where('name', $roleName)->exists()) {
            $this->command->warn("Role {$roleName} does not exist, skipping assignment.");
            return;
        }

        $user->syncRoles([$roleName]);
    }

    private function roleForType(string $type): string
    {
        return $type;
    }
}
