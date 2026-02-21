<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->index();
            $table->longText('content_md');
            $table->string('language', 10)->default('ar')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['key', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_prompts');
    }
};

