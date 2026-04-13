<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_lead_submissions', function (Blueprint $table) {
            $table->id();

            $table->string('platform', 20)->index();
            $table->string('account_id')->index();

            $table->string('lead_id', 120);
            $table->timestamp('created_time')->nullable()->index();

            $table->string('campaign_id')->nullable()->index();
            $table->string('adset_id')->nullable()->index();
            $table->string('ad_id')->nullable()->index();
            $table->string('form_id')->nullable()->index();

            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();

            $table->json('extra_data')->nullable();
            $table->json('raw_payload')->nullable();

            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['platform', 'account_id', 'lead_id'], 'ads_lead_submissions_dedupe');
            $table->index(['platform', 'account_id', 'campaign_id'], 'ads_leads_campaign_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_lead_submissions');
    }
};

