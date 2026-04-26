<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_target_executive_directors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_target_id')->constrained('sales_targets')->cascadeOnDelete();
            $table->string('line_type', 100);
            $table->decimal('value', 15, 2)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['sales_target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_target_executive_directors');
    }
};
