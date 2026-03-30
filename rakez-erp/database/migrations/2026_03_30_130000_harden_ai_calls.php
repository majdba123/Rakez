<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_calls', function (Blueprint $table) {
            $table->string('idempotency_key', 80)->nullable()->after('initiated_by');
            $table->string('transcript_analysis_status', 32)->nullable()->after('call_summary');
            $table->text('transcript_analysis_error')->nullable()->after('transcript_analysis_status');

            $table->unique('idempotency_key');
            $table->index('transcript_analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_calls', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['transcript_analysis_status']);
            $table->dropColumn(['idempotency_key', 'transcript_analysis_status', 'transcript_analysis_error']);
        });
    }
};
