<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove contract status 'ready' from the contracts table.
     * Existing contracts with status 'ready' are set to 'approved'.
     */
    public function up(): void
    {
        DB::table('contracts')->where('status', 'ready')->update(['status' => 'approved']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending'");
        }
    }

    /**
     * Re-add 'ready' to the enum (reverse for rollback).
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed', 'ready') DEFAULT 'pending'");
        }
    }
};
