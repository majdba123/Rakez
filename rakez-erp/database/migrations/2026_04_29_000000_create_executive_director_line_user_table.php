<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('executive_director_line_user')) {
            return;
        }

        Schema::create('executive_director_line_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('executive_director_line_id')
                ->constrained('executive_director_lines', indexName: 'edl_user_edl_id_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', indexName: 'edl_user_user_id_fk')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['executive_director_line_id', 'user_id'], 'edl_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_director_line_user');
    }
};
