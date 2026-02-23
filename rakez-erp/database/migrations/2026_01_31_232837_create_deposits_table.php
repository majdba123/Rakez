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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_reservation_id')->constrained('sales_reservations')->onDelete('cascade');
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('contract_unit_id')->constrained('contract_units')->onDelete('cascade');
            
            // Deposit details
            $table->decimal('amount', 16, 2);
            $table->enum('payment_method', ['bank_transfer', 'cash', 'bank_financing']);
            $table->string('client_name');
            $table->date('payment_date');
            
            // Commission source (determines refund logic)
            $table->enum('commission_source', ['owner', 'buyer'])->default('owner');
            
            // Status tracking
            $table->enum('status', ['pending', 'received', 'refunded', 'confirmed'])->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            // Additional metadata
            $table->text('notes')->nullable();
            $table->string('claim_file_path')->nullable()->comment('Path to commission claim file');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['sales_reservation_id', 'status']);
            $table->index(['contract_id', 'payment_date']);
            $table->index(['status', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
