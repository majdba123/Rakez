<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * تشغيل سيدرات قاعدة البيانات (بيانات أولية بالعربية السعودية).
     */
    public function run(): void
    {
        // ترتيب السيدرات: الأدمن أولاً ثم الأدوار والصلاحيات ثم الفرق والمستخدمين
        $this->call([
            AdminUserSeeder::class,
            ArabicSeedDataSeeder::class,
            RolesAndPermissionsSeeder::class,
            CommissionRolesSeeder::class,
            UserTypesSeeder::class,
          //  TeamsSeeder::class,
          //  UsersSeeder::class,
         //   TasksSeeder::class,
            AssistantKnowledgeSalesSeeder::class,
          //  ContractsSeeder::class,
         //   MarketingSeeder::class,
         //   SalesSeeder::class,
         //   NegotiationsPaymentsSeeder::class,
          //  CreditSeeder::class,
           // AccountingSeeder::class,
         //   HRSeeder::class,
            AISeeder::class,
         ///   NotificationsSeeder::class,
            AiCallScriptSeeder::class,
            // Add other seeders here
        ]);
    }
}
