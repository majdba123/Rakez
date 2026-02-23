<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Add units as JSON array to store multiple units
            $table->json('units')->nullable()->after('developer_number');

            // Drop old unit columns (after migration is complete, can be removed later)
            // $table->dropColumn(['units_count', 'unit_type', 'average_unit_price', 'total_units_value']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('units');
        });
    }
};
