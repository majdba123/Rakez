<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interaction_logs', function (Blueprint $table) {
            $table->string('correlation_id', 80)->nullable()->after('session_id');
            $table->index('correlation_id');
        });

        Schema::table('ai_audit_trail', function (Blueprint $table) {
            $table->string('correlation_id', 80)->nullable()->after('user_id');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interaction_logs', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });

        Schema::table('ai_audit_trail', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });
    }
};
