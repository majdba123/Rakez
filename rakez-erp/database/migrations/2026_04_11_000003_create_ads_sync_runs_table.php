<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index(); // campaigns|insights|leads|exports
            $table->string('platform', 20)->nullable()->index();
            $table->string('account_id')->nullable()->index();

            $table->string('status', 30)->default('running')->index(); // running|completed|failed
            $table->text('error')->nullable();

            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();

            $table->timestamps();

            $table->index(['type', 'platform', 'account_id', 'started_at'], 'ads_sync_runs_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_sync_runs');
    }
};

