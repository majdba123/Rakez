<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_reward_recipients', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_reward_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('recipient_type')->nullable();
            $table->unsignedBigInteger('sales_reservation_id')->nullable();
            $table->unsignedBigInteger('sales_reservation_participant_id')->nullable();
            $table->string('source_scope')->nullable();
            $table->string('source_type')->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('project_reward_id', 'prr_reward_id_fk')->references('id')->on('project_rewards')->cascadeOnDelete();
            $table->foreign('user_id', 'prr_user_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sales_reservation_id', 'prr_reservation_id_fk')->references('id')->on('sales_reservations')->nullOnDelete();
            $table->foreign('sales_reservation_participant_id', 'prr_participant_id_fk')->references('id')->on('sales_reservation_participants')->nullOnDelete();
            $table->foreign('approved_by', 'prr_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by', 'prr_rejected_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('paid_by', 'prr_paid_by_fk')->references('id')->on('users')->nullOnDelete();

            $table->index('project_reward_id', 'prr_reward_id_ix');
            $table->index('user_id', 'prr_user_id_ix');
            $table->index('status', 'prr_status_ix');
            $table->index(['user_id', 'status'], 'prr_user_status_ix');
            $table->index('approved_at', 'prr_approved_at_ix');
            $table->index('paid_at', 'prr_paid_at_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_reward_recipients');
    }
};
