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
        Schema::create('reservation_payment_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_reservation_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 16, 2);
            $table->string('description', 255)->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamps();

            // Indexes
            $table->index(['sales_reservation_id', 'due_date'], 'idx_reservation_due');
            $table->index(['status', 'due_date'], 'idx_status_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_payment_installments');
    }
};

