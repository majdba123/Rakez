<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_financing_trackers', function (Blueprint $table) {
            $table->boolean('is_cash_workflow')->default(false)->after('is_supported_bank');
        });
    }

    public function down(): void
    {
        Schema::table('credit_financing_trackers', function (Blueprint $table) {
            $table->dropColumn('is_cash_workflow');
        });
    }
};
