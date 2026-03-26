<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reason text when montage review is rejected (disapproved).
     */
    public function up(): void
    {
        Schema::table('montage_departments', function (Blueprint $table) {
            $table->text('rejection_comment')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('montage_departments', function (Blueprint $table) {
            $table->dropColumn('rejection_comment');
        });
    }
};
