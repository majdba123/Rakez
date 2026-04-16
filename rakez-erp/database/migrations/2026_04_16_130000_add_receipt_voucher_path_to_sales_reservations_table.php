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
            $table->string('receipt_voucher_path', 500)
                ->nullable()
                ->after('voucher_pdf_path')
                ->comment('Uploaded receipt voucher file path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_reservations', function (Blueprint $table) {
            $table->dropColumn('receipt_voucher_path');
        });
    }
};
