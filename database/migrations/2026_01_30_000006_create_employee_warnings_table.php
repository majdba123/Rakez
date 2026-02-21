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
        Schema::create('employee_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['performance', 'attendance', 'behavior', 'other'])->default('performance');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->boolean('is_auto_generated')->default(false);
            $table->date('warning_date');
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'warning_date'], 'idx_user_warning_date');
            $table->index(['type', 'warning_date'], 'idx_type_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_warnings');
    }
};

