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
        Schema::create('claim_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path', 500)->nullable();
            $table->json('file_data');
            $table->timestamps();

            // Index
            $table->index('sales_reservation_id', 'idx_claim_reservation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_files');
    }
};



