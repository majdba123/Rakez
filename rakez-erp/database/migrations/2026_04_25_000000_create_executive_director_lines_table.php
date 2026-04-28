<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('executive_director_lines')) {
            return;
        }

        // Fresh installs only (legacy `sales_target_executive_directors` is handled in 000001).
        if (Schema::hasTable('sales_target_executive_directors')) {
            return;
        }

        Schema::create('executive_director_lines', function (Blueprint $table) {
            $table->id();
            $table->string('line_type', 100);
            $table->decimal('value', 15, 2)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_director_lines');
    }
};
