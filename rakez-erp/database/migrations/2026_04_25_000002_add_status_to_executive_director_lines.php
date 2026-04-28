<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('executive_director_lines')) {
            return;
        }

        if (! Schema::hasColumn('executive_director_lines', 'status')) {
            Schema::table('executive_director_lines', function (Blueprint $table) {
                $table->string('status', 32)->default('pending')->after('value');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('executive_director_lines') && Schema::hasColumn('executive_director_lines', 'status')) {
            Schema::table('executive_director_lines', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
