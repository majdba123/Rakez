<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads_insight_rows', function (Blueprint $table) {
            if (! Schema::hasColumn('ads_insight_rows', 'leads')) {
                $table->unsignedBigInteger('leads')->default(0)->after('conversions');
            }
        });

        // Replace unsafe upsert key (platform+entity_id+date+breakdown) with account-aware key.
        try {
            Schema::table('ads_insight_rows', function (Blueprint $table) {
                $table->dropUnique('ads_insight_rows_upsert_key');
            });
        } catch (\Throwable) {
            // Ignore when running on a fresh database where the index does not exist yet.
        }

        Schema::table('ads_insight_rows', function (Blueprint $table) {
            $table->unique(
                ['platform', 'account_id', 'level', 'entity_id', 'date_start', 'date_stop', 'breakdown_hash'],
                'ads_insight_rows_upsert_key_v2'
            );
        });
    }

    public function down(): void
    {
        try {
            Schema::table('ads_insight_rows', function (Blueprint $table) {
                $table->dropUnique('ads_insight_rows_upsert_key_v2');
            });
        } catch (\Throwable) {
            // Ignore if already dropped.
        }

        Schema::table('ads_insight_rows', function (Blueprint $table) {
            $table->unique(
                ['platform', 'entity_id', 'date_start', 'date_stop', 'breakdown_hash'],
                'ads_insight_rows_upsert_key'
            );

            if (Schema::hasColumn('ads_insight_rows', 'leads')) {
                $table->dropColumn('leads');
            }
        });
    }
};
