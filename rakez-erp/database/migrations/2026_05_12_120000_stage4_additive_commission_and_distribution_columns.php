<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('project_commission_setting_id')
                ->nullable()
                ->after('team_responsible')
                ->constrained('project_commission_settings')
                ->nullOnDelete();
            $table->string('calculation_formula_key')->nullable()->after('project_commission_setting_id');
            $table->boolean('calculated_by_project_setting')->default(false)->after('calculation_formula_key');
        });

        Schema::table('commission_distributions', function (Blueprint $table) {
            $table->string('source_scope')->nullable()->after('type');
            $table->string('source_type')->nullable()->after('source_scope');
        });
    }

    public function down(): void
    {
        Schema::table('commission_distributions', function (Blueprint $table) {
            $table->dropColumn(['source_scope', 'source_type']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_commission_setting_id');
            $table->dropColumn(['calculation_formula_key', 'calculated_by_project_setting']);
        });
    }
};
