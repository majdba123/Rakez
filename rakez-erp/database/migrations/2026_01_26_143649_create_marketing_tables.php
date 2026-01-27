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
        // 1. Leads - Lead tracking
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_info');
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->foreignId('project_id')->nullable()->constrained('contracts')->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('assigned_to');
            $table->index('status');
        });

        // 2. Marketing Projects - Marketing project assignments
        Schema::create('marketing_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('status')->default('active');
            $table->foreignId('assigned_team_leader')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('contract_id');
            $table->index('status');
        });

        // 3. Marketing Project Teams - Team assignments per project
        Schema::create('marketing_project_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_project_id')->constrained('marketing_projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role')->nullable();
            $table->timestamps();

            $table->index('marketing_project_id');
            $table->index('user_id');
        });

        // 4. Developer Marketing Plans - Developer marketing plans
        Schema::create('developer_marketing_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->decimal('average_cpm', 15, 2)->default(0);
            $table->decimal('average_cpc', 15, 2)->default(0);
            $table->decimal('marketing_value', 15, 2)->default(0);
            $table->bigInteger('expected_impressions')->default(0);
            $table->bigInteger('expected_clicks')->default(0);
            $table->timestamps();

            $table->index('contract_id');
        });

        // 5. Employee Marketing Plans - Employee marketing plans
        Schema::create('employee_marketing_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_project_id')->constrained('marketing_projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('commission_value', 15, 2)->default(0);
            $table->decimal('marketing_value', 15, 2)->default(0);
            $table->json('platform_distribution')->nullable();
            $table->json('campaign_distribution')->nullable();
            $table->timestamps();

            $table->index('marketing_project_id');
            $table->index('user_id');
        });

        // 6. Marketing Campaigns - Individual campaigns
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_marketing_plan_id')->constrained('employee_marketing_plans')->onDelete('cascade');
            $table->string('platform');
            $table->string('campaign_type');
            $table->decimal('budget', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('employee_marketing_plan_id');
            $table->index('platform');
        });

        // 7. Marketing Tasks - Daily tasks (Note: marketing_tasks already exists, so we might need to modify it or skip if it matches)
        // Checking existing table: marketing_tasks already exists from 2026_01_26_100004_create_marketing_tasks_table.php
        // It has: contract_id, task_name, marketer_id, participating_marketers_count, design_link, design_number, design_description, status, created_by
        // The plan asks for: id, marketing_project_id, assigned_to, title, description, status, due_date, created_by
        // I will add missing columns to the existing table in a separate migration or modify this one if I were creating it.
        // Since I'm creating a NEW migration for the rest, I'll add an ALTER for marketing_tasks if needed.
        Schema::table('marketing_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_tasks', 'marketing_project_id')) {
                $table->foreignId('marketing_project_id')->nullable()->constrained('marketing_projects')->onDelete('set null');
            }
            if (!Schema::hasColumn('marketing_tasks', 'due_date')) {
                $table->date('due_date')->nullable();
            }
        });

        // 8. Daily Deposits - Daily booking deposits
        Schema::create('daily_deposits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('booking_id')->nullable(); // Placeholder for sales integration
            $table->foreignId('project_id')->constrained('contracts')->onDelete('cascade');
            $table->timestamps();

            $table->index('project_id');
            $table->index('date');
        });

        // 9. Expected Bookings - Expected bookings calculations
        Schema::create('expected_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_project_id')->constrained('marketing_projects')->onDelete('cascade');
            $table->integer('direct_communications')->default(0);
            $table->integer('hand_raises')->default(0);
            $table->integer('expected_bookings_count')->default(0);
            $table->decimal('expected_booking_value', 15, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->index('marketing_project_id');
        });

        // 10. Marketing Settings - System-wide marketing settings
        Schema::create('marketing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 11. Project Media - Links to edited images/videos
        Schema::create('project_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('type'); // image/video
            $table->string('url', 500);
            $table->string('department'); // montage/photography
            $table->timestamps();

            $table->index('contract_id');
            $table->index('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_media');
        Schema::dropIfExists('marketing_settings');
        Schema::dropIfExists('expected_bookings');
        Schema::dropIfExists('daily_deposits');
        
        Schema::table('marketing_tasks', function (Blueprint $table) {
            $table->dropForeign(['marketing_project_id']);
            $table->dropColumn(['marketing_project_id', 'due_date']);
        });

        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('employee_marketing_plans');
        Schema::dropIfExists('developer_marketing_plans');
        Schema::dropIfExists('marketing_project_teams');
        Schema::dropIfExists('marketing_projects');
        Schema::dropIfExists('leads');
    }
};
