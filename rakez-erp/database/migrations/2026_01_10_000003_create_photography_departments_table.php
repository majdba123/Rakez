<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قسم التصوير - Photography Department Table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photography_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('image_url', 500)->nullable();       // رابط الصورة
            $table->string('video_url', 500)->nullable();       // رابط الفيديو
            $table->text('description')->nullable();             // الوصف
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Ensure one photography department per contract
            $table->unique('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photography_departments');
    }
};

