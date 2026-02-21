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
        Schema::table('employee_marketing_plans', function (Blueprint $table) {
            $table->decimal('marketing_percent', 5, 2)->nullable()->after('marketing_value');
            $table->decimal('direct_contact_percent', 5, 2)->nullable()->after('marketing_percent');
        });

        Schema::table('developer_marketing_plans', function (Blueprint $table) {
            $table->decimal('marketing_percent', 5, 2)->nullable()->after('marketing_value');
            $table->decimal('direct_contact_percent', 5, 2)->nullable()->after('marketing_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_marketing_plans', function (Blueprint $table) {
            $table->dropColumn(['marketing_percent', 'direct_contact_percent']);
        });

        Schema::table('developer_marketing_plans', function (Blueprint $table) {
            $table->dropColumn(['marketing_percent', 'direct_contact_percent']);
        });
    }
};
