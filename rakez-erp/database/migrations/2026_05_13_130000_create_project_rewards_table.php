<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_rewards', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sales_reservation_id');
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('project_reward_setting_id')->nullable();

            $table->string('calculation_mode')->default('percentage_of_sale');
            $table->decimal('calculation_base_amount', 15, 2)->default(0);
            $table->decimal('reward_percentage', 8, 4)->nullable();
            $table->string('source')->default('company');
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->boolean('tax_enabled')->default(false);
            $table->decimal('vat_percentage', 8, 4)->default(15);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('distribution_pool_amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('sales_reservation_id', 'pr_reservation_id_fk')->references('id')->on('sales_reservations')->restrictOnDelete();
            $table->foreign('contract_id', 'pr_contract_id_fk')->references('id')->on('contracts')->restrictOnDelete();
            $table->foreign('project_reward_setting_id', 'pr_setting_id_fk')->references('id')->on('project_reward_settings')->nullOnDelete();
            $table->foreign('created_by', 'pr_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by', 'pr_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by', 'pr_rejected_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('paid_by', 'pr_paid_by_fk')->references('id')->on('users')->nullOnDelete();

            $table->index('sales_reservation_id', 'pr_reservation_id_ix');
            $table->index('contract_id', 'pr_contract_id_ix');
            $table->index('status', 'pr_status_ix');
            $table->index('approved_at', 'pr_approved_at_ix');
            $table->index('paid_at', 'pr_paid_at_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_rewards');
    }
};
