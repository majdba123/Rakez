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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nationality', 100)->nullable()->after('marital_status');
            $table->string('job_title', 100)->nullable()->after('nationality');
            $table->string('department', 100)->nullable()->after('job_title');
            $table->text('additional_benefits')->nullable()->after('department');
            $table->integer('probation_period_days')->nullable()->after('additional_benefits');
            $table->enum('work_type', ['full_time', 'part_time', 'contract', 'remote'])->default('full_time')->after('probation_period_days');
            $table->string('signature_path', 500)->nullable()->after('work_type');
            $table->boolean('work_phone_approval')->default(false)->after('signature_path');
            $table->boolean('logo_usage_approval')->default(false)->after('work_phone_approval');
            $table->boolean('is_active')->default(true)->after('logo_usage_approval');
            $table->date('contract_end_date')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nationality',
                'job_title',
                'department',
                'additional_benefits',
                'probation_period_days',
                'work_type',
                'signature_path',
                'work_phone_approval',
                'logo_usage_approval',
                'is_active',
                'contract_end_date',
            ]);
        });
    }
};

