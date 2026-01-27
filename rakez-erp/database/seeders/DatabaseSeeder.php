<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed users before roles/permissions so role assignment can attach
        $this->call([
            AdminUserSeeder::class,
            ArabicSeedDataSeeder::class,
            RolesAndPermissionsSeeder::class,
            // Add other seeders here
        ]);
    }
}
