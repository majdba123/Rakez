<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exclusive_project_requests', function (Blueprint $table) {
            $table->string('unit_type', 100)->nullable()->after('estimated_units');
            $table->decimal('estimated_unit_price', 16, 2)->nullable()->after('unit_type');
            $table->decimal('total_value', 20, 2)->nullable()->after('estimated_unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exclusive_project_requests', function (Blueprint $table) {
            $table->dropColumn(['unit_type', 'estimated_unit_price', 'total_value']);
        });
    }
};
