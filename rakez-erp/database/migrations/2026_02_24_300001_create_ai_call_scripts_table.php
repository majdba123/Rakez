<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_call_scripts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('target_type', ['lead', 'customer', 'both'])->default('both');
            $table->string('language', 5)->default('ar');
            $table->json('questions');
            $table->text('greeting_text');
            $table->text('closing_text');
            $table->unsignedTinyInteger('max_retries_per_question')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_scripts');
    }
};
