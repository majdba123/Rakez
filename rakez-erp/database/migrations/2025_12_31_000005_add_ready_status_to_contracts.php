<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support MODIFY COLUMN, so we skip this migration for SQLite
        // The enum constraint is handled at the application level for SQLite
        if (DB::getDriverName() !== 'sqlite') {
            // Change enum to include 'ready' status (MySQL only)
            DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed', 'ready') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // SQLite doesn't support MODIFY COLUMN
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending'");
        }
    }
};

