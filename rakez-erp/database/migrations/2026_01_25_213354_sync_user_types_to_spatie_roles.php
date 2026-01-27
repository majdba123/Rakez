<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure roles and permissions are seeded
        Artisan::call('db:seed', [
            '--class' => RolesAndPermissionsSeeder::class,
            '--force' => true
        ]);

        // Sync roles for all users based on their type and is_manager status
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                $roleName = $user->type;

                // Handle sales leader special case
                if ($user->type === 'sales' && $user->is_manager) {
                    $roleName = 'sales_leader';
                }

                // Check if role exists before assigning
                if (Role::where('name', $roleName)->exists()) {
                    $user->assignRole($roleName);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all roles from all users
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                $user->roles()->detach();
            }
        });
    }
};
