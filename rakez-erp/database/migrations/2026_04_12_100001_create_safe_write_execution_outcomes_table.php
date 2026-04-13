<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safe_write_execution_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_key', 120);
            $table->string('idempotency_key', 128);
            $table->string('proposal_fingerprint', 64);
            $table->unsignedBigInteger('sales_reservation_action_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'action_key', 'idempotency_key'], 'safe_write_exec_user_action_idem_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safe_write_execution_outcomes');
    }
};
