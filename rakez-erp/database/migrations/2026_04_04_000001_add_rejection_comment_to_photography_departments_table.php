<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photography_departments', function (Blueprint $table) {
            if (!Schema::hasColumn('photography_departments', 'rejection_comment')) {
                $table->text('rejection_comment')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('photography_departments', function (Blueprint $table) {
            if (Schema::hasColumn('photography_departments', 'rejection_comment')) {
                $table->dropColumn('rejection_comment');
            }
        });
    }
};
