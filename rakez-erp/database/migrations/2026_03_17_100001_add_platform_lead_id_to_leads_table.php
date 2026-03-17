<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('platform_lead_id', 100)->nullable()->after('campaign_id');
            $table->unique(['campaign_platform', 'platform_lead_id'], 'leads_platform_lead_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('leads_platform_lead_id_unique');
            $table->dropColumn('platform_lead_id');
        });
    }
};
