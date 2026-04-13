<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_realtime_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_realtime_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('event_type', 80)->index();
            $table->string('state_before', 50)->nullable();
            $table->string('state_after', 50)->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['session_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_realtime_session_events');
    }
};
