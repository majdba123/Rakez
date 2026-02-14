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
        Schema::create('marketing_budget_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_project_id')->constrained('marketing_projects')->onDelete('cascade');
            $table->enum('plan_type', ['employee', 'developer']);
            $table->foreignId('employee_marketing_plan_id')->nullable();
            $table->foreignId('developer_marketing_plan_id')->nullable();
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->json('platform_distribution')->nullable(); // توزيع النسب على المنصات
            $table->json('platform_objectives')->nullable(); // توزيع الأهداف داخل كل منصة (Impression%, Lead%, Direct Contact%)
            $table->json('platform_costs')->nullable(); // أسعار CPL والتواصل المباشر لكل منصة
            $table->json('cost_source')->nullable(); // مصدر السعر (manual/auto) لكل منصة
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('average_booking_value', 15, 2)->default(0);
            $table->json('calculated_results')->nullable(); // نتائج الحسابات التلقائية
            $table->timestamps();

            $table->index('marketing_project_id');
            $table->index('plan_type');
            $table->index('employee_marketing_plan_id');
            $table->index('developer_marketing_plan_id');

            // Add foreign keys with shorter custom names to avoid MySQL 64-char limit
            $table->foreign('employee_marketing_plan_id', 'mbd_emp_plan_fk')
                ->references('id')
                ->on('employee_marketing_plans')
                ->onDelete('cascade');

            $table->foreign('developer_marketing_plan_id', 'mbd_dev_plan_fk')
                ->references('id')
                ->on('developer_marketing_plans')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_budget_distributions');
    }
};
