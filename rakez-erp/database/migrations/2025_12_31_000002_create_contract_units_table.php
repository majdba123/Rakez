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
        Schema::create('contract_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('second_party_data_id')->constrained('second_party_data')->onDelete('cascade');

            // Unit details from CSV
            $table->string('unit_type')->nullable();
            $table->string('unit_number')->nullable();
            $table->integer('count')->default(0);
            $table->string('status')->default("pending");
            $table->decimal('price', 16, 2)->default(0);
            $table->decimal('total_price', 16, 2)->default(0);

            $table->decimal('area', 12, 2)->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_units');
    }
};

