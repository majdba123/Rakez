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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_unit_id')->constrained('contract_units')->onDelete('cascade');
            $table->foreignId('sales_reservation_id')->constrained('sales_reservations')->onDelete('cascade');
            
            // Commission amounts
            $table->decimal('final_selling_price', 16, 2);
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->decimal('vat', 16, 2)->default(0);
            $table->decimal('marketing_expenses', 16, 2)->default(0);
            $table->decimal('bank_fees', 16, 2)->default(0);
            $table->decimal('net_amount', 16, 2)->default(0);
            
            // Commission source and metadata
            $table->enum('commission_source', ['owner', 'buyer'])->default('owner');
            $table->string('team_responsible')->nullable();
            
            // Status tracking
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['contract_unit_id', 'status']);
            $table->index(['sales_reservation_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
