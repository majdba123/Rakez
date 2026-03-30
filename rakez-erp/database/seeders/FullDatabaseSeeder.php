<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @deprecated استخدم {@see DatabaseSeeder} أو `php artisan db:seed` — نفس المسار الكامل.
 * يُبقى للتوافق مع سكربتات قديمة تشغّل `--class=FullDatabaseSeeder`.
 */
class FullDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SeedManifest::defaultPipeline());
    }
}
