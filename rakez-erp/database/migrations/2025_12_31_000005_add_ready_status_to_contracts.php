<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change enum to include 'ready' status
        DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed', 'ready') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending'");
    }
};

