<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->enum('customer_type', ['lead', 'customer'])->default('lead');
            $table->string('customer_name')->nullable();
            $table->string('phone_number');
            $table->foreignId('script_id')->nullable()->constrained('ai_call_scripts')->nullOnDelete();
            $table->string('twilio_call_sid')->nullable()->unique();
            $table->enum('status', [
                'pending',
                'ringing',
                'in_progress',
                'completed',
                'failed',
                'no_answer',
                'busy',
                'cancelled',
            ])->default('pending');
            $table->enum('direction', ['outbound'])->default('outbound');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('total_questions_asked')->default(0);
            $table->unsignedSmallInteger('total_questions_answered')->default(0);
            $table->text('call_summary')->nullable();
            $table->decimal('sentiment_score', 3, 2)->nullable();
            $table->unsignedSmallInteger('current_question_index')->default(0);
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('lead_id');
            $table->index('status');
            $table->index('initiated_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
