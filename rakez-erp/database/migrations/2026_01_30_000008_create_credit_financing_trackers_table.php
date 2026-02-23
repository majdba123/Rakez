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
        Schema::create('credit_financing_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            
            // Stage 1: Client Communication (48 hours)
            $table->enum('stage_1_status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->string('bank_name')->nullable();
            $table->decimal('client_salary', 12, 2)->nullable();
            $table->enum('employment_type', ['government', 'private'])->nullable();
            $table->timestamp('stage_1_completed_at')->nullable();
            $table->timestamp('stage_1_deadline')->nullable();
            
            // Stage 2: Submit to Bank (5 days)
            $table->enum('stage_2_status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->timestamp('stage_2_completed_at')->nullable();
            $table->timestamp('stage_2_deadline')->nullable();
            
            // Stage 3: Valuation Issuance (3 days)
            $table->enum('stage_3_status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->timestamp('stage_3_completed_at')->nullable();
            $table->timestamp('stage_3_deadline')->nullable();
            
            // Stage 4: Appraiser Visit (2 days)
            $table->enum('stage_4_status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->string('appraiser_name')->nullable();
            $table->timestamp('stage_4_completed_at')->nullable();
            $table->timestamp('stage_4_deadline')->nullable();
            
            // Stage 5: Banking Procedures (5 days)
            $table->enum('stage_5_status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->timestamp('stage_5_completed_at')->nullable();
            $table->timestamp('stage_5_deadline')->nullable();
            
            // Overall
            $table->boolean('is_supported_bank')->default(false);
            $table->enum('overall_status', ['in_progress', 'completed', 'rejected', 'cancelled'])->default('in_progress');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['sales_reservation_id', 'overall_status'], 'idx_reservation_status');
            $table->index('overall_status', 'idx_overall_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_financing_trackers');
    }
};



