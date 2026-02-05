<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FullDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TeamsSeeder::class,
            UsersSeeder::class,
            ContractsSeeder::class,
            MarketingSeeder::class,
            SalesSeeder::class,
            NegotiationsPaymentsSeeder::class,
            CreditSeeder::class,
            AccountingSeeder::class,
            HRSeeder::class,
            AISeeder::class,
            NotificationsSeeder::class,
        ]);
    }
}
