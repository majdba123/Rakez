<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE tasks MODIFY team_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tasks ALTER COLUMN team_id DROP NOT NULL');
        } else {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->change();
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('tasks')->whereNull('team_id')->exists()) {
            throw new \RuntimeException('Cannot rollback: some tasks have null team_id. Assign team_id to those rows first.');
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE tasks MODIFY team_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tasks ALTER COLUMN team_id SET NOT NULL');
        } else {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable(false)->change();
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();
        });
    }
};
