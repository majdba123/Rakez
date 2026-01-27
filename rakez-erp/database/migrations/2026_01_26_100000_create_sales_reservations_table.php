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
        Schema::create('sales_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('contract_unit_id')->constrained('contract_units')->onDelete('cascade');
            $table->foreignId('marketing_employee_id')->constrained('users')->onDelete('cascade');
            
            $table->enum('status', ['under_negotiation', 'confirmed', 'cancelled'])->default('under_negotiation');
            $table->enum('reservation_type', ['confirmed_reservation', 'negotiation']);
            $table->date('contract_date');
            $table->text('negotiation_notes')->nullable();
            
            // Client data
            $table->string('client_name');
            $table->string('client_mobile', 50);
            $table->string('client_nationality', 100);
            $table->string('client_iban', 100);
            
            // Payment data
            $table->enum('payment_method', ['bank_transfer', 'cash', 'bank_financing']);
            $table->decimal('down_payment_amount', 16, 2);
            $table->enum('down_payment_status', ['refundable', 'non_refundable']);
            $table->enum('purchase_mechanism', ['cash', 'supported_bank', 'unsupported_bank']);
            
            // PDF + metadata
            $table->string('voucher_pdf_path', 500)->nullable();
            $table->json('snapshot')->nullable()->comment('Frozen project/unit/employee data for voucher');
            
            // Status timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['contract_id', 'status', 'created_at'], 'idx_contract_status');
            $table->index(['contract_unit_id', 'status'], 'idx_unit_status');
            $table->index(['marketing_employee_id', 'created_at'], 'idx_employee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_reservations');
    }
};
