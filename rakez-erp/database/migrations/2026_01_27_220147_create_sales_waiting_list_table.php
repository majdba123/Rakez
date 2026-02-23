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
        Schema::create('sales_waiting_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('contract_unit_id')->constrained('contract_units')->onDelete('cascade');
            $table->foreignId('sales_staff_id')->constrained('users')->onDelete('cascade');
            $table->string('client_name');
            $table->string('client_mobile');
            $table->string('client_email')->nullable();
            $table->integer('priority')->default(1);
            $table->enum('status', ['waiting', 'converted', 'cancelled', 'expired'])->default('waiting');
            $table->text('notes')->nullable();
            $table->foreignId('converted_to_reservation_id')->nullable()->constrained('sales_reservations')->onDelete('set null');
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['contract_unit_id', 'status']);
            $table->index(['sales_staff_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_waiting_list');
    }
};
