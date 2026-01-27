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
        Schema::create('exclusive_project_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('project_name');
            $table->string('developer_name');
            $table->string('developer_contact');
            $table->text('project_description')->nullable();
            $table->integer('estimated_units')->nullable();
            $table->string('location_city');
            $table->string('location_district')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'contract_completed'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->onDelete('set null');
            $table->timestamp('contract_completed_at')->nullable();
            $table->string('contract_pdf_path')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('requested_by');
            $table->index('status');
            $table->index('approved_by');
            $table->index('contract_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exclusive_project_requests');
    }
};
