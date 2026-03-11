<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add status (pending/approved) for montage department approval flow, same as photography.
     */
    public function up(): void
    {
        Schema::table('montage_departments', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('montage_departments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
