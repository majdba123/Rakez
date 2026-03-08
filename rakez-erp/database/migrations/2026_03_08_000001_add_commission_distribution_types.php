<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FULL_TYPE_ENUM = [
        'lead_generation',
        'persuasion',
        'closing',
        'team_leader',
        'assistant_pm',
        'project_manager',
        'owner',
        'sales_manager',
        'projects_department',
        'management',
        'ceo',
        'external_marketer',
        'other',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $enumList = implode("','", self::FULL_TYPE_ENUM);
            DB::statement("ALTER TABLE commission_distributions MODIFY COLUMN type ENUM('{$enumList}') NOT NULL");
        }
        // SQLite and others: column may already be string; no change needed for new values
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $original = [
                'lead_generation', 'persuasion', 'closing', 'team_leader',
                'sales_manager', 'project_manager', 'external_marketer', 'other',
            ];
            $enumList = implode("','", $original);
            DB::statement("ALTER TABLE commission_distributions MODIFY COLUMN type ENUM('{$enumList}') NOT NULL");
        }
    }
};
