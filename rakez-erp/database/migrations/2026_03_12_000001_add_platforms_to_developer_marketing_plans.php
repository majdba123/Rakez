<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores per-platform breakdown: platform key/name, CPM, CPC, views, clicks.
     */
    public function up(): void
    {
        Schema::table('developer_marketing_plans', function (Blueprint $table) {
            $table->json('platforms')->nullable()->after('expected_clicks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('developer_marketing_plans', function (Blueprint $table) {
            $table->dropColumn('platforms');
        });
    }
};
