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
        Schema::create('hr_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->dateTime('due_at');
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['in_progress', 'completed', 'could_not_complete'])->default('in_progress');
            $table->text('cannot_complete_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['assigned_to', 'created_at']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_tasks');
    }
};
