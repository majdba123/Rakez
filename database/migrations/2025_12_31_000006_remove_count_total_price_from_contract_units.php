<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropColumn(['count', 'total_price']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->integer('count')->default(0)->after('unit_number');
            $table->decimal('total_price', 16, 2)->default(0)->after('price');
        });
    }
};

