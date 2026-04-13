<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Artisan::call('db:seed', [
                '--class' => RolesAndPermissionsSeeder::class,
                '--force' => true,
            ]);
        } catch (\Exception $exception) {
            // Ignore transient seeding failures during migration bootstrap.
        }

        User::query()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->chunk(50, function ($users): void {
                foreach ($users as $user) {
                    try {
                        $roleName = $user->type;

                        if ($user->type === 'sales' && $user->is_manager) {
                            $roleName = 'sales_leader';
                        }

                        if (Role::where('name', $roleName)->exists()) {
                            $user->assignRole($roleName);
                        }
                    } catch (\Exception $exception) {
                        continue;
                    }
                }

                usleep(5000);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        User::query()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->chunk(100, function ($users): void {
                foreach ($users as $user) {
                    $user->roles()->detach();
                }
            });
    }
};
