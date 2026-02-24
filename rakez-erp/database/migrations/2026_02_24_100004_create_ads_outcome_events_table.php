<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_outcome_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64);
            $table->string('platform', 20)->index();
            $table->string('outcome_type', 30);
            $table->timestamp('occurred_at');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();

            $table->decimal('value', 14, 4)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('crm_stage')->nullable();
            $table->unsignedTinyInteger('score')->nullable();

            $table->string('lead_id')->nullable();
            $table->json('hashed_identifiers')->nullable();
            $table->json('click_ids')->nullable();
            $table->json('payload')->nullable();
            $table->json('platform_response')->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'platform'], 'ads_outcome_events_idempotency');
            $table->index(['status', 'retry_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_outcome_events');
    }
};
