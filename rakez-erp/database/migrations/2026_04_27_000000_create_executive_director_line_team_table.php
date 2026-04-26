<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_director_line_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('executive_director_line_id')
                ->constrained('executive_director_lines')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['executive_director_line_id', 'team_id'], 'edl_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_director_line_team');
    }
};
