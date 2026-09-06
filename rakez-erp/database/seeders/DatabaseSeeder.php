<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * تشغيل سيدرات قاعدة البيانات (بيانات أولية وتجريبية بالعربية السعودية).
     * الترتيب الموحّد معرّف في {@see SeedManifest::defaultPipeline()}.
     */
    public function run(): void
    {
        if (app()->environment('production') && ! $this->productionDemoSeedingExplicitlyAllowed()) {
            throw new RuntimeException(
                'Refusing to run the Rakez demo/default seed pipeline in production. '
                . 'Set ALLOW_PRODUCTION_DEMO_SEEDING=true only for an intentional, disposable environment.'
            );
        }

        $this->call(SeedManifest::defaultPipeline());
    }

    private function productionDemoSeedingExplicitlyAllowed(): bool
    {
        return filter_var(
            env('ALLOW_PRODUCTION_DEMO_SEEDING', false),
            FILTER_VALIDATE_BOOL,
        );
    }
}
