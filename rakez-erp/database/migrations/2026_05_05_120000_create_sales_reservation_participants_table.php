<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_reservation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_reservation_id')
                ->constrained('sales_reservations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->boolean('did_bring')->default(false);
            $table->boolean('did_convince')->default(false);
            $table->boolean('did_close')->default(false);
            $table->decimal('weight', 10, 2)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Explicit short name — MySQL max identifier length is 64 chars (auto-generated name exceeded it).
            $table->unique(['sales_reservation_id', 'user_id'], 'sr_participants_reservation_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_reservation_participants');
    }
};
