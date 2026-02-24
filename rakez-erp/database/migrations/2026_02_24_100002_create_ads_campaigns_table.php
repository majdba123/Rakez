<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('account_id');
            $table->string('campaign_id');
            $table->string('name')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('objective')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'account_id', 'campaign_id']);
        });

        Schema::create('ads_ad_sets', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('account_id');
            $table->string('campaign_id');
            $table->string('ad_set_id');
            $table->string('name')->nullable();
            $table->string('status', 50)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'account_id', 'ad_set_id']);
        });

        Schema::create('ads_ads', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('account_id');
            $table->string('ad_set_id');
            $table->string('ad_id');
            $table->string('name')->nullable();
            $table->string('status', 50)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'account_id', 'ad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_ads');
        Schema::dropIfExists('ads_ad_sets');
        Schema::dropIfExists('ads_campaigns');
    }
};
