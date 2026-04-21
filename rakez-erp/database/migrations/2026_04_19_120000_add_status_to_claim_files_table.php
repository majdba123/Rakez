<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_files', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('claim_files', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
