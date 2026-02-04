<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_marketing_spends', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_marketing_spends');
    }
};
