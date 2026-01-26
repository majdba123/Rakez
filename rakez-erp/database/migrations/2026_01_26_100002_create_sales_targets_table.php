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
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('marketer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('contract_unit_id')->nullable()->constrained('contract_units')->onDelete('cascade');
            
            $table->enum('target_type', ['reservation', 'negotiation', 'closing']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['new', 'in_progress', 'completed'])->default('new');
            $table->text('leader_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['leader_id', 'created_at'], 'idx_leader');
            $table->index(['marketer_id', 'status', 'start_date', 'end_date'], 'idx_marketer_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
