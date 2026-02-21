<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('module', 120)->index();
            $table->string('page_key', 180)->nullable()->index();
            $table->string('title', 255);
            $table->longText('content_md');
            $table->json('tags')->nullable();
            $table->json('roles')->nullable();
            $table->json('permissions')->nullable();
            $table->string('language', 10)->default('ar')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->index(['module', 'page_key']);
            $table->index(['is_active', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_knowledge_entries');
    }
};

