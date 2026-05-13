<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_reward_settings', function (Blueprint $table) {
            $table->id();

            // Project/Contract
            $table->unsignedBigInteger('contract_id');

            // Calculation mode
            $table->string('calculation_mode')
                ->default('percentage_of_sale')
                ->comment('percentage_of_sale or manual_amount');
            $table->decimal('reward_percentage', 5, 2)
                ->nullable()
                ->comment('Required if calculation_mode = percentage_of_sale');

            // Source
            $table->string('source')
                ->default('company')
                ->comment('developer or company');

            // Tax
            $table->boolean('tax_enabled')
                ->default(false);
            $table->decimal('vat_percentage', 5, 2)
                ->default(15.00);

            // Distribution percentages - Assigned project team participants
            $table->decimal('assigned_bring_percentage', 8, 2)
                ->default(0);
            $table->decimal('assigned_convince_percentage', 8, 2)
                ->default(0);
            $table->decimal('assigned_close_percentage', 8, 2)
                ->default(0);

            // Distribution percentages - Outside project team participants
            $table->decimal('outside_bring_percentage', 8, 2)
                ->default(0);
            $table->decimal('outside_convince_percentage', 8, 2)
                ->default(0);
            $table->decimal('outside_close_percentage', 8, 2)
                ->default(0);

            // Management users and percentages
            $table->unsignedBigInteger('ceo_user_id')->nullable();
            $table->decimal('ceo_percentage', 8, 2)
                ->default(0);

            $table->unsignedBigInteger('sales_manager_user_id')->nullable();
            $table->decimal('sales_manager_percentage', 8, 2)
                ->default(0);

            $table->unsignedBigInteger('sales_leader_user_id')->nullable();
            $table->decimal('sales_leader_percentage', 8, 2)
                ->default(0);

            $table->unsignedBigInteger('group_leader_user_id')->nullable();
            $table->decimal('group_leader_percentage', 8, 2)
                ->default(0);

            $table->unsignedBigInteger('external_marketer_user_id')->nullable();
            $table->decimal('external_marketer_percentage', 8, 2)
                ->default(0);

            // Status
            $table->boolean('is_active')
                ->default(true);

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contract_id', 'prs_contract_id_fk')->references('id')->on('contracts')->restrictOnDelete();
            $table->foreign('ceo_user_id', 'prs_ceo_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sales_manager_user_id', 'prs_sm_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sales_leader_user_id', 'prs_sl_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('group_leader_user_id', 'prs_gl_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('external_marketer_user_id', 'prs_em_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'prs_created_by_fk')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index('contract_id', 'prs_contract_id_ix');
            $table->index(['contract_id', 'is_active'], 'prs_contract_active_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_reward_settings');
    }
};
