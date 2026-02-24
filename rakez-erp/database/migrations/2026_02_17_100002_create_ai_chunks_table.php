<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Single-tenant: no tenant_id.
     * Embedding: MySQL/pgsql use embedding_json (JSON); SQLite has no embedding column (tools-only).
     */
    public function up(): void
    {
        Schema::create('ai_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('ai_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content_text');
            $table->json('meta_json')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->index('document_id');
            $table->index(['document_id', 'chunk_index']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'pgsql' || $driver === 'sqlite') {
            Schema::table('ai_chunks', function (Blueprint $table) {
                $table->json('embedding_json')->nullable()->after('content_hash');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chunks');
    }
};
