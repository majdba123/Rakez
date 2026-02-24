<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_performance_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('composite_score', 5, 2);
            $table->json('factor_scores');
            $table->json('strengths');
            $table->json('weaknesses');
            $table->string('trend', 20)->default('stable');
            $table->json('project_type_affinity');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->unique(['user_id', 'period_start', 'period_end'], 'emp_perf_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_performance_scores');
    }
};
