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
        Schema::create('accounting_salary_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('month')->comment('Month (1-12)');
            $table->integer('year')->comment('Year (e.g., 2026)');
            $table->decimal('base_salary', 15, 2)->default(0)->comment('Base monthly salary');
            $table->decimal('total_commissions', 15, 2)->default(0)->comment('Sum of all commissions for the month');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Base salary + commissions');
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Unique constraint: one distribution per user per month/year
            $table->unique(['user_id', 'month', 'year']);
            
            // Index for querying by period
            $table->index(['year', 'month']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_salary_distributions');
    }
};
