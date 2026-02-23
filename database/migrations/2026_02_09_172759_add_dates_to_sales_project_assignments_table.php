<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_project_assignments', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('sales_project_assignments', 'start_date')) {
                $table->date('start_date')->nullable()->after('assigned_by');
            }
            if (!Schema::hasColumn('sales_project_assignments', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        // Try to drop unique constraint if it exists (MySQL/MariaDB only; SQLite does not support DROP INDEX this way)
        if (DB::getDriverName() !== 'sqlite') {
            try {
                DB::statement('ALTER TABLE `sales_project_assignments` DROP INDEX `unique_leader_contract`');
            } catch (\Exception $e) {
                Log::debug('Migration add_dates_to_sales_project_assignments: could not drop unique_leader_contract index', ['exception' => $e->getMessage()]);
            }
        }

        // Add index for better query performance if it doesn't exist
        Schema::table('sales_project_assignments', function (Blueprint $table) {
            if (!Schema::hasIndex('sales_project_assignments', 'idx_leader_dates')) {
                $table->index(['leader_id', 'start_date', 'end_date'], 'idx_leader_dates');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_project_assignments', function (Blueprint $table) {
            // Drop index
            $table->dropIndex('idx_leader_dates');

            // Drop date fields
            $table->dropColumn(['start_date', 'end_date']);

            // Restore unique constraint
            $table->unique(['leader_id', 'contract_id'], 'unique_leader_contract');
        });
    }
};
