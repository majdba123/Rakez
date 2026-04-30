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
            if (! Schema::hasColumn('executive_director_line_team', 'value_target')) {
                $table->decimal('value_target', 12, 2)
                    ->nullable()
                    ->after('team_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('executive_director_line_team')) {
            return;
        }

        Schema::table('executive_director_line_team', function (Blueprint $table) {
            if (Schema::hasColumn('executive_director_line_team', 'value_target')) {
                $table->dropColumn('value_target');
            }
        });
    }
};
