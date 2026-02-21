<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add deposit_id to link daily_deposits to the source Deposit (accounting).
     * Used to avoid duplicate DailyDeposit when a deposit is confirmed and to backfill.
     */
    public function up(): void
    {
        Schema::table('daily_deposits', function (Blueprint $table) {
            $table->foreignId('deposit_id')->nullable()->after('id')->constrained('deposits')->nullOnDelete();
            $table->unique('deposit_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_deposits', function (Blueprint $table) {
            $table->dropForeign(['deposit_id']);
            $table->dropUnique(['deposit_id']);
        });
    }
};
