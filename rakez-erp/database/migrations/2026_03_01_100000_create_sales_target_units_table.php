<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot table: one sales target can have multiple units assigned.
     */
    public function up(): void
    {
        Schema::create('sales_target_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_target_id')->constrained('sales_targets')->onDelete('cascade');
            $table->foreignId('contract_unit_id')->constrained('contract_units')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['sales_target_id', 'contract_unit_id'], 'sales_target_units_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_target_units');
    }
};
