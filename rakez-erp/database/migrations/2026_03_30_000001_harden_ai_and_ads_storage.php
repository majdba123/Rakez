<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('uploaded_by_user_id');
        });

        Schema::table('ads_outcome_events', function (Blueprint $table) {
            $table->timestamp('next_attempt_at')->nullable()->after('last_attempted_at');
            $table->text('dead_letter_reason')->nullable()->after('last_error');
            $table->string('provider_currency', 10)->nullable()->after('currency');

            $table->index(['status', 'next_attempt_at']);
        });

        $this->addPgVectorColumn();
    }

    public function down(): void
    {
        $this->dropPgVectorColumn();

        Schema::table('ads_outcome_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_attempt_at']);
            $table->dropColumn([
                'next_attempt_at',
                'dead_letter_reason',
                'provider_currency',
            ]);
        });

        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_user_id');
        });
    }

    private function addPgVectorColumn(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! $this->pgVectorInstalled() || $this->columnExists('ai_chunks', 'embedding_vector')) {
            return;
        }

        DB::statement('ALTER TABLE ai_chunks ADD COLUMN embedding_vector vector(1536)');
        DB::statement('CREATE INDEX ai_chunks_embedding_vector_idx ON ai_chunks USING ivfflat (embedding_vector vector_cosine_ops)');
    }

    private function dropPgVectorColumn(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! $this->columnExists('ai_chunks', 'embedding_vector')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS ai_chunks_embedding_vector_idx');
        DB::statement('ALTER TABLE ai_chunks DROP COLUMN embedding_vector');
    }

    private function pgVectorInstalled(): bool
    {
        $row = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'vector') AS installed");

        return (bool) ($row->installed ?? false);
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
};
