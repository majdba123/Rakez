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
        Schema::table('sales_reservations', function (Blueprint $table) {
            // Accounting confirmation fields
            $table->boolean('down_payment_confirmed')->default(false)->after('down_payment_status');
            $table->foreignId('down_payment_confirmed_by')->nullable()->after('down_payment_confirmed')->constrained('users')->nullOnDelete();
            $table->timestamp('down_payment_confirmed_at')->nullable()->after('down_payment_confirmed_by');
            
            // Commission and tax fields
            $table->decimal('brokerage_commission_percent', 5, 2)->nullable()->after('down_payment_confirmed_at');
            $table->enum('commission_payer', ['seller', 'buyer'])->nullable()->after('brokerage_commission_percent');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('commission_payer');
            
            // Credit tracking status
            $table->enum('credit_status', ['pending', 'in_progress', 'title_transfer', 'sold', 'rejected'])->default('pending')->after('tax_amount');

            // Index for credit queries
            $table->index('credit_status', 'idx_credit_status');
            $table->index(['status', 'credit_status'], 'idx_status_credit_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_reservations', function (Blueprint $table) {
            $table->dropIndex('idx_credit_status');
            $table->dropIndex('idx_status_credit_status');
            
            $table->dropForeign(['down_payment_confirmed_by']);
            $table->dropColumn([
                'down_payment_confirmed',
                'down_payment_confirmed_by',
                'down_payment_confirmed_at',
                'brokerage_commission_percent',
                'commission_payer',
                'tax_amount',
                'credit_status',
            ]);
        });
    }
};



