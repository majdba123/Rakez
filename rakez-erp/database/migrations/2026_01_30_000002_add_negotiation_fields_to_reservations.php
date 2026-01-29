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
            $table->string('negotiation_reason', 255)->nullable()->after('negotiation_notes');
            $table->decimal('proposed_price', 16, 2)->nullable()->after('negotiation_reason');
            $table->date('evacuation_date')->nullable()->after('proposed_price');
            $table->timestamp('approval_deadline')->nullable()->after('evacuation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_reservations', function (Blueprint $table) {
            $table->dropColumn([
                'negotiation_reason',
                'proposed_price',
                'evacuation_date',
                'approval_deadline',
            ]);
        });
    }
};

