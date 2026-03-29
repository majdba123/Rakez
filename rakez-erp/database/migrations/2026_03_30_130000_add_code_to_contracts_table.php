<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-generated reference: type initial + city code + side (see ContractCodeGenerator).
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->unique()->after('contract_type');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
