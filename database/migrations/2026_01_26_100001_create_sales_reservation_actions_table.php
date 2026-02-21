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
        Schema::create('sales_reservation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_reservation_id')->constrained('sales_reservations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action_type', ['lead_acquisition', 'persuasion', 'closing']);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            
            // Index
            $table->index(['sales_reservation_id', 'created_at'], 'idx_reservation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_reservation_actions');
    }
};
