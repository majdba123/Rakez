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
        Schema::create('negotiation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->string('negotiation_reason');
            $table->decimal('original_price', 16, 2);
            $table->decimal('proposed_price', 16, 2);
            $table->text('manager_notes')->nullable();
            $table->timestamp('deadline_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['status', 'deadline_at'], 'idx_status_deadline');
            $table->index('requested_by', 'idx_requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negotiation_approvals');
    }
};

