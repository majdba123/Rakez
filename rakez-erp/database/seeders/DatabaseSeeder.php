<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * تشغيل سيدرات قاعدة البيانات (بيانات أولية بالعربية السعودية).
     * الترتيب الموحّد معرّف في {@see SeedManifest::defaultPipeline()}.
     */
    public function run(): void
    {
        $this->call(SeedManifest::defaultPipeline());
    }
}
