<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('executive_director_line_team_group')) {
            return;
        }

        Schema::create('executive_director_line_team_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('executive_director_line_id')
                ->constrained('executive_director_lines')
                ->cascadeOnDelete();
            $table->foreignId('team_group_id')
                ->constrained('team_groups')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(
                ['executive_director_line_id', 'team_group_id'],
                'edl_team_group_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_director_line_team_group');
    }
};
