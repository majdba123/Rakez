<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('executive_director_line_team_group')) {
            return;
        }

        Schema::table('executive_director_line_team_group', function (Blueprint $table) {
            if (! Schema::hasColumn('executive_director_line_team_group', 'group_status')) {
                $table->string('group_status')->default('pending')->after('value_target');
            }
            if (! Schema::hasColumn('executive_director_line_team_group', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('group_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('executive_director_line_team_group')) {
            return;
        }

        Schema::table('executive_director_line_team_group', function (Blueprint $table) {
            if (Schema::hasColumn('executive_director_line_team_group', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
            if (Schema::hasColumn('executive_director_line_team_group', 'group_status')) {
                $table->dropColumn('group_status');
            }
        });
    }
};
