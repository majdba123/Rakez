<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketing developer order records (Credit department).
     */
    public function up(): void
    {
        Schema::create('order_marketing_developers', function (Blueprint $table) {
            $table->id();
            $table->string('developer_name');
            $table->string('developer_number');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_marketing_developers');
    }
};
