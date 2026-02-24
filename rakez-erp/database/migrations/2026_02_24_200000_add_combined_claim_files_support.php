<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_files', function (Blueprint $table) {
            $table->foreignId('sales_reservation_id')->nullable()->change();
            $table->boolean('is_combined')->default(false)->after('sales_reservation_id');
            $table->string('claim_type', 50)->nullable()->after('is_combined');
            $table->text('notes')->nullable()->after('claim_type');
            $table->decimal('total_claim_amount', 14, 2)->nullable()->after('notes');
        });

        Schema::create('claim_file_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_file_id')->constrained('claim_files')->cascadeOnDelete();
            $table->foreignId('sales_reservation_id')->constrained('sales_reservations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['claim_file_id', 'sales_reservation_id'], 'cfr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_file_reservations');

        Schema::table('claim_files', function (Blueprint $table) {
            $table->dropColumn(['is_combined', 'claim_type', 'notes', 'total_claim_amount']);
            $table->foreignId('sales_reservation_id')->nullable(false)->change();
        });
    }
};
