<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_realtime_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50)->index();
            $table->string('transport', 50)->default('websocket');
            $table->string('transport_mode', 50)->default('control_plane_only');
            $table->string('transport_status', 50)->default('not_connected');
            $table->string('section', 100)->nullable();
            $table->string('provider_model', 100)->nullable();
            $table->string('provider_session_id', 191)->nullable()->index();
            $table->string('rollback_target', 50)->default('voice_fallback');
            $table->uuid('correlation_id')->nullable()->index();
            $table->unsignedInteger('duration_limit_seconds')->default(900);
            $table->unsignedInteger('max_reconnects')->default(3);
            $table->unsignedInteger('reconnect_count')->default(0);
            $table->unsignedInteger('turn_number')->default(0);
            $table->unsignedInteger('estimated_input_tokens')->default(0);
            $table->unsignedInteger('estimated_output_tokens')->default(0);
            $table->unsignedInteger('estimated_total_tokens')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_realtime_sessions');
    }
};
