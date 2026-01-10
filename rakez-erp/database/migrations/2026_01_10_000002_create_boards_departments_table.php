<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قسم اللوحات - Boards Department Table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->boolean('has_ads')->default(false);           // هل يوجد إعلانات
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Ensure one boards department per contract
            $table->unique('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boards_departments');
    }
};

