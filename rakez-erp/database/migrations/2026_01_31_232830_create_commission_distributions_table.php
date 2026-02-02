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
        Schema::create('commission_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Distribution type and details
            $table->enum('type', [
                'lead_generation',
                'persuasion',
                'closing',
                'team_leader',
                'sales_manager',
                'project_manager',
                'external_marketer',
                'other'
            ]);
            $table->string('external_name')->nullable()->comment('Name for external marketers or other');
            $table->string('bank_account')->nullable();
            
            // Amount calculation
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 16, 2);
            
            // Status tracking
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['commission_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_distributions');
    }
};
