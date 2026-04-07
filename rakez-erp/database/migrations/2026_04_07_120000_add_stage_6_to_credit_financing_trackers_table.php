<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Six-stage sold-unit credit workflow: stage 6 = period before transfer (الإفراغ), duration varies by bank mode.
     */
    public function up(): void
    {
        Schema::table('credit_financing_trackers', function (Blueprint $table) {
            $table->enum('stage_6_status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending')->after('stage_5_deadline');
            $table->timestamp('stage_6_completed_at')->nullable()->after('stage_6_status');
            $table->timestamp('stage_6_deadline')->nullable()->after('stage_6_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_financing_trackers', function (Blueprint $table) {
            $table->dropColumn(['stage_6_status', 'stage_6_completed_at', 'stage_6_deadline']);
        });
    }
};
