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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'cv_path')) {
                $table->string('cv_path')->nullable()->after('team_id');
            }
            if (!Schema::hasColumn('users', 'contract_path')) {
                $table->string('contract_path')->nullable()->after('cv_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'contract_path')) {
                $table->dropColumn('contract_path');
            }
            if (Schema::hasColumn('users', 'cv_path')) {
                $table->dropColumn('cv_path');
            }
        });
    }
};


