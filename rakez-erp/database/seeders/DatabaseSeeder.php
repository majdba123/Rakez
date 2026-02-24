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
            CommissionRolesSeeder::class,
            TeamsSeeder::class,
            UsersSeeder::class,
            TasksSeeder::class,
            AssistantKnowledgeSalesSeeder::class,
            ContractsSeeder::class,
            MarketingSeeder::class,
            SalesSeeder::class,
            NegotiationsPaymentsSeeder::class,
            CreditSeeder::class,
            AccountingSeeder::class,
            HRSeeder::class,
            AISeeder::class,
            NotificationsSeeder::class,
            AiCallScriptSeeder::class,
            // Add other seeders here
        ]);
    }
}
