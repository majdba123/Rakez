<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Note: The 'team' field (string) and 'team_id' field (foreign key) serve different purposes:
     * - 'team' is used for simple string-based team grouping in Sales module (e.g., "Team A", "Team B")
     * - 'team_id' is a proper foreign key relationship to the teams table for structured team management
     * 
     * Both fields are intentionally kept for backward compatibility and different use cases.
     */
    public function up(): void
    {
        // This migration serves as documentation only.
        // No schema changes needed - both fields are intentionally present.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No changes to reverse
    }
};
