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
        Schema::table('contract_units', function (Blueprint $table) {
            $table->decimal('street_width', 10, 2)
                ->nullable()
                ->after('private_area_m2')
                ->comment('عرض الشارع بالمتر');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropColumn('street_width');
        });
    }
};
