<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_group_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_group_id')->constrained('team_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('team_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_group_leaders');
    }
};
