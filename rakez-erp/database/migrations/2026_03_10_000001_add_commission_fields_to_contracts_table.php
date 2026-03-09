<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration is now a no-op; commission fields live only on contract_infos.
     */
    public function up(): void
    {
        // Intentionally left empty.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty.
    }
};

