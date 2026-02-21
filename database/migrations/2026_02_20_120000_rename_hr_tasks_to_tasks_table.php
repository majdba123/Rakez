<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Rename hr_tasks to tasks for system-wide task management.
     */
    public function up(): void
    {
        Schema::rename('hr_tasks', 'tasks');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('tasks', 'hr_tasks');
    }
};
