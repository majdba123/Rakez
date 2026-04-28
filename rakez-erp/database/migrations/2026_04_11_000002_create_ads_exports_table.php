<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_exports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index(); // e.g. leads_csv

            $table->string('status', 30)->default('queued')->index(); // queued|running|completed|failed
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();

            $table->json('filters')->nullable();
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path')->nullable();
            $table->string('download_filename')->nullable();

            $table->unsignedBigInteger('row_count')->default(0);
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_exports');
    }
};

