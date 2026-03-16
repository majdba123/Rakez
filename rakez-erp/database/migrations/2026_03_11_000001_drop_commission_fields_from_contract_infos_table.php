<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_infos', function (Blueprint $table) {
            $table->dropColumn(['commission_percent', 'commission_from']);
        });
    }
g
    public function down(): void
    {
        Schema::table('contract_infos', function (Blueprint $table) {
            $table->decimal('commission_percent', 8, 2)->nullable()->after('agreement_duration_days');
            $table->string('commission_from')->nullable()->after('commission_percent');
        });
    }
};
