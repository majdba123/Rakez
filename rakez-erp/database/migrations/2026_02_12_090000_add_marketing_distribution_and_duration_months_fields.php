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
        Schema::table('contract_infos', function (Blueprint $table) {
            if (!Schema::hasColumn('contract_infos', 'agreement_duration_months')) {
                $table->unsignedInteger('agreement_duration_months')->nullable()->after('agreement_duration_days');
            }
        });

        Schema::table('employee_marketing_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_marketing_plans', 'campaign_distribution_by_platform')) {
                $table->json('campaign_distribution_by_platform')->nullable()->after('campaign_distribution');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_marketing_plans', function (Blueprint $table) {
            if (Schema::hasColumn('employee_marketing_plans', 'campaign_distribution_by_platform')) {
                $table->dropColumn('campaign_distribution_by_platform');
            }
        });

        Schema::table('contract_infos', function (Blueprint $table) {
            if (Schema::hasColumn('contract_infos', 'agreement_duration_months')) {
                $table->dropColumn('agreement_duration_months');
            }
        });
    }
};
