<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('executive_director_line_team')) {
            return;
        }

        Schema::table('executive_director_line_team', function (Blueprint $table) {
            if (! Schema::hasColumn('executive_director_line_team', 'team_status')) {
                $table->string('team_status')->default('pending')->after('value_target');
            }
            if (! Schema::hasColumn('executive_director_line_team', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('team_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('executive_director_line_team')) {
            return;
        }

        Schema::table('executive_director_line_team', function (Blueprint $table) {
            if (Schema::hasColumn('executive_director_line_team', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
            if (Schema::hasColumn('executive_director_line_team', 'team_status')) {
                $table->dropColumn('team_status');
            }
        });
    }
};
