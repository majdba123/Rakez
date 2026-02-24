<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_insight_rows', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('account_id');
            $table->string('level', 20);
            $table->string('entity_id');
            $table->date('date_start');
            $table->date('date_stop');
            $table->string('breakdown_hash', 64)->default('none');

            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('spend', 14, 4)->default(0);
            $table->string('spend_currency', 10)->default('USD');
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('revenue', 14, 4)->default(0);
            $table->unsignedBigInteger('video_views')->default(0);
            $table->unsignedBigInteger('reach')->default(0);

            $table->json('raw_metrics')->nullable();
            $table->timestamps();

            $table->unique(
                ['platform', 'entity_id', 'date_start', 'date_stop', 'breakdown_hash'],
                'ads_insight_rows_upsert_key'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_insight_rows');
    }
};
