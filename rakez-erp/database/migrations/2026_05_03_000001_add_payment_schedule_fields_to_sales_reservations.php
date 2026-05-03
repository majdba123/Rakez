<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_reservations', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('down_payment_status');
            }
            if (! Schema::hasColumn('sales_reservations', 'first_payment')) {
                $table->decimal('first_payment', 16, 2)->nullable()->after('delivery_date');
            }
            if (! Schema::hasColumn('sales_reservations', 'first_payment_date')) {
                $table->date('first_payment_date')->nullable()->after('first_payment');
            }
            if (! Schema::hasColumn('sales_reservations', 'account')) {
                $table->enum('account', ['basic', 'from_developer'])->nullable()->after('first_payment_date');
            }
        });

        Schema::table('reservation_payment_installments', function (Blueprint $table) {
            $table->date('due_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_reservations', function (Blueprint $table) {
            $columns = ['delivery_date', 'first_payment', 'first_payment_date', 'account'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('sales_reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('reservation_payment_installments', function (Blueprint $table) {
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
