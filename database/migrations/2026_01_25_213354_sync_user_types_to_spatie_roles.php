<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure roles and permissions are seeded
        try {
            Artisan::call('db:seed', [
                '--class' => RolesAndPermissionsSeeder::class,
                '--force' => true
            ]);
        } catch (\Exception $e) {
            // If seeder fails (e.g., deadlock), continue - roles might already exist
            Log::debug('Migration sync_user_types_to_spatie_roles: RolesAndPermissionsSeeder failed', ['exception' => $e->getMessage()]);
        }

        // Sync roles for all users based on their type and is_manager status
        // Use smaller chunks and add delay to prevent deadlocks
        User::chunk(50, function ($users) {
            foreach ($users as $user) {
                try {
                    $roleName = $user->type;

                    // Handle sales leader special case
                    if ($user->type === 'sales' && $user->is_manager) {
                        $roleName = 'sales_leader';
                    }

                    // Check if role exists before assigning
                    if (Role::where('name', $roleName)->exists()) {
                        $user->assignRole($roleName);
                    }
                } catch (\Exception $e) {
                    Log::debug('Migration sync_user_types_to_spatie_roles: role assignment failed for user', ['user_id' => $user->id, 'exception' => $e->getMessage()]);
                    continue;
                }
            }
            // Small delay between chunks to prevent lock conflicts
            usleep(5000); // 5ms
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
