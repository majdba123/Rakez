<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $this->hardenNegotiationApprovalsUserForeignKeys($driver);
        $this->hardenAiCallsAndLeadsForeignKeys();
        $this->hardenAssistantUpdatedByForeignKeys();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasTable('assistant_prompts')) {
            Schema::table('assistant_prompts', function (Blueprint $table) {
                $table->dropForeign(['updated_by']);
            });
        }

        if (Schema::hasTable('assistant_knowledge_entries')) {
            Schema::table('assistant_knowledge_entries', function (Blueprint $table) {
                $table->dropForeign(['updated_by']);
            });
        }

        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'last_ai_call_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeign(['last_ai_call_id']);
            });
        }

        if (Schema::hasTable('ai_calls') && Schema::hasColumn('ai_calls', 'lead_id')) {
            Schema::table('ai_calls', function (Blueprint $table) {
                $table->dropForeign(['lead_id']);
            });
        }

        $this->revertNegotiationApprovalsUserForeignKeys($driver);
    }

    private function hardenNegotiationApprovalsUserForeignKeys(string $driver): void
    {
        if (! Schema::hasTable('negotiation_approvals')) {
            return;
        }

        Schema::table('negotiation_approvals', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approved_by']);
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE negotiation_approvals MODIFY requested_by BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE negotiation_approvals ALTER COLUMN requested_by DROP NOT NULL');
        }
        // SQLite: column remains NOT NULL; re-add restrictive FKs only for approved_by below.

        Schema::table('negotiation_approvals', function (Blueprint $table) use ($driver) {
            if ($driver === 'sqlite') {
                $table->foreign('requested_by')->references('id')->on('users');
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

                return;
            }

            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function revertNegotiationApprovalsUserForeignKeys(string $driver): void
    {
        if (! Schema::hasTable('negotiation_approvals')) {
            return;
        }

        Schema::table('negotiation_approvals', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approved_by']);
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE negotiation_approvals MODIFY requested_by BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE negotiation_approvals ALTER COLUMN requested_by SET NOT NULL');
        }

        Schema::table('negotiation_approvals', function (Blueprint $table) {
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    private function hardenAiCallsAndLeadsForeignKeys(): void
    {
        if (Schema::hasTable('ai_calls') && Schema::hasTable('leads') && Schema::hasColumn('ai_calls', 'lead_id')) {
            Schema::table('ai_calls', function (Blueprint $table) {
                $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            });
        }

        if (Schema::hasTable('leads') && Schema::hasTable('ai_calls') && Schema::hasColumn('leads', 'last_ai_call_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreign('last_ai_call_id')->references('id')->on('ai_calls')->nullOnDelete();
            });
        }
    }

    private function hardenAssistantUpdatedByForeignKeys(): void
    {
        if (Schema::hasTable('assistant_knowledge_entries') && Schema::hasColumn('assistant_knowledge_entries', 'updated_by')) {
            Schema::table('assistant_knowledge_entries', function (Blueprint $table) {
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('assistant_prompts') && Schema::hasColumn('assistant_prompts', 'updated_by')) {
            Schema::table('assistant_prompts', function (Blueprint $table) {
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }
};
