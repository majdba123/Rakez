<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_salary_distributions', function (Blueprint $table) {
            if (!Schema::hasColumn('accounting_salary_distributions', 'total_rewards')) {
                $table->decimal('total_rewards', 15, 2)
                    ->default(0)
                    ->after('total_commissions')
                    ->comment('Sum of approved project rewards for the month');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_salary_distributions', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_salary_distributions', 'total_rewards')) {
                $table->dropColumn('total_rewards');
            }
        });
    }
};
