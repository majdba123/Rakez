<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Side: project orientation (N, W, E, S). Contract type: category label (free text).
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('side', 1)->nullable()->after('district_id');
            $table->string('contract_type', 100)->nullable()->after('side');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['side', 'contract_type']);
        });
    }
};
