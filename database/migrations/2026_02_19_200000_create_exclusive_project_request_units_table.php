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
        Schema::create('exclusive_project_request_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exclusive_project_request_id');
            $table->foreign('exclusive_project_request_id', 'excl_proj_req_units_request_id_foreign')
                ->references('id')->on('exclusive_project_requests')
                ->onDelete('cascade');
            $table->string('unit_type', 100);
            $table->unsignedInteger('count');
            $table->decimal('average_price', 16, 2)->nullable();
            $table->timestamps();

            $table->index('exclusive_project_request_id', 'excl_proj_req_units_request_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exclusive_project_request_units');
    }
};
