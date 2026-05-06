<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_commission_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->string('commission_source');
            $table->decimal('commission_percentage', 8, 2);

            $table->decimal('assigned_bring_percentage', 8, 2)->default(0);
            $table->decimal('assigned_convince_percentage', 8, 2)->default(0);
            $table->decimal('assigned_close_percentage', 8, 2)->default(0);

            $table->decimal('outside_bring_percentage', 8, 2)->default(0);
            $table->decimal('outside_convince_percentage', 8, 2)->default(0);
            $table->decimal('outside_close_percentage', 8, 2)->default(0);

            $table->unsignedBigInteger('ceo_user_id')->nullable();
            $table->decimal('ceo_percentage', 8, 2)->default(0);

            $table->unsignedBigInteger('sales_manager_user_id')->nullable();
            $table->decimal('sales_manager_percentage', 8, 2)->default(0);

            $table->unsignedBigInteger('sales_leader_user_id')->nullable();
            $table->decimal('sales_leader_percentage', 8, 2)->default(0);

            $table->unsignedBigInteger('group_leader_user_id')->nullable();
            $table->decimal('group_leader_percentage', 8, 2)->default(0);

            $table->unsignedBigInteger('external_marketer_user_id')->nullable();
            $table->decimal('external_marketer_percentage', 8, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // Explicit short FK names — some auto-generated Laravel names exceed MySQL 64-char limit.
            $table->foreign('project_id', 'pcs_project_id_fk')->references('id')->on('contracts')->restrictOnDelete();
            $table->foreign('ceo_user_id', 'pcs_ceo_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sales_manager_user_id', 'pcs_sm_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sales_leader_user_id', 'pcs_sl_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('group_leader_user_id', 'pcs_gl_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('external_marketer_user_id', 'pcs_em_uid_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'pcs_created_by_fk')->references('id')->on('users')->nullOnDelete();

            $table->index('project_id', 'pcs_project_id_ix');
            $table->index('is_active', 'pcs_is_active_ix');
            $table->index(['project_id', 'is_active'], 'pcs_project_active_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_commission_settings');
    }
};
