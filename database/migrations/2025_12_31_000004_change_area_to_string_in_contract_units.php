<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->string('area', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->decimal('area', 12, 2)->nullable()->change();
        });
    }
};

