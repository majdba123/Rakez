<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('row_errors')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_imports');
    }
};
