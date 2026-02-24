<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_call_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_call_id')->constrained('ai_calls')->cascadeOnDelete();
            $table->enum('role', ['ai', 'client'])->default('ai');
            $table->text('content');
            $table->string('question_key')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->unsignedInteger('timestamp_in_call')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('ai_call_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_messages');
    }
};
