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
        // Call the admin user seeder
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            ArabicSeedDataSeeder::class,
            // Add other seeders here
        ]);
    }
}
