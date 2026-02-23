<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('team')->index();
                $table->foreign('team_id')
                    ->references('id')
                    ->on('teams')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_id')) {
                try {
                    $table->dropForeign(['team_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('team_id');
            }
        });
    }
};


