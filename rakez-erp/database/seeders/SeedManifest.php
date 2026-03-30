<?php

namespace Database\Seeders;

/**
 * مصدر واحد لترتيب السييدرات والاعتماديات بينها.
 *
 * الترتيب: أساس (مصادقة/أدوار) → فرق ومستخدمون → بيانات عربية تجريبية → مهام ومعرفة
 * → عقود ومشروع حصري → تسويق ومبيعات → ائتمان ومحاسبة وموارد بشرية → ذكاء اصطناعي.
 *
 * سييدرات تُشغَّل يدوياً عند الحاجة (لا تُدرَج هنا):
 * - {@see CommissionTestDataSeeder}
 * - {@see NotificationsSeeder}
 */
final class SeedManifest
{
    /**
     * المسار الافتراضي الكامل لـ `php artisan db:seed`.
     *
     * @return array<int, class-string<\Illuminate\Database\Seeder>>
     */
    public static function defaultPipeline(): array
    {
        return [
            // 1) أساس النظام والصلاحيات
            AdminUserSeeder::class,
            RolesAndPermissionsSeeder::class,
            CommissionRolesSeeder::class,
            UserTypesSeeder::class,

            // 2) فرق ثم مستخدمون ثابتون + عشوائيون (يعتمد على الفرق)
            TeamsSeeder::class,
            UsersSeeder::class,

            // 3) مستخدمون وعقود تجريبية بالعربية (يعتمد على الأدوار والفرق؛ يجب أن يكون بعد UsersSeeder لربط الأدوار)
            ArabicSeedDataSeeder::class,

            // 4) مهام ومعرفة المساعد
            TasksSeeder::class,
            AssistantKnowledgeSalesSeeder::class,

            // 5) عقود جماعية ثم ربط فريق قائد المبيعات بعقود مكتملة (لمشاريع الفريق في Postman/API)
            ContractsSeeder::class,
            SalesLeaderTeamProjectsLinkSeeder::class,
            ExclusiveProjectCsvScenarioSeeder::class,

            // 6) تسويق ومبيعات ومتابعة
            MarketingSeeder::class,
            SalesSeeder::class,
            SalesMarketingExclusiveLinkSeeder::class,
            NegotiationsPaymentsSeeder::class,

            // 7) ائتمان ومحاسبة وموارد بشرية
            CreditSeeder::class,
            AccountingSeeder::class,
            HRSeeder::class,

            // 8) ذكاء اصطناعي
            AISeeder::class,
            AiCallScriptSeeder::class,
        ];
    }
}
