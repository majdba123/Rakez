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
        Schema::create('marketing_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('task_name');
            $table->foreignId('marketer_id')->constrained('users')->onDelete('cascade');
            $table->integer('participating_marketers_count')->default(4);
            
            $table->string('design_link', 500)->nullable();
            $table->string('design_number', 100)->nullable();
            $table->text('design_description')->nullable();
            
            $table->enum('status', ['new', 'in_progress', 'completed'])->default('new');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['contract_id', 'status'], 'idx_contract_status');
            $table->index(['marketer_id', 'created_at'], 'idx_marketer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_tasks');
    }
};
