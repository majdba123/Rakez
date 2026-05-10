<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('contracts', 'archive_requested_at')) {
                $table->timestamp('archive_requested_at')->nullable()->after('is_complete_second');
            }
            if (!Schema::hasColumn('contracts', 'archive_requested_by')) {
                $table->foreignId('archive_requested_by')->nullable()->after('archive_requested_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('contracts', 'archive_confirmed_at')) {
                $table->timestamp('archive_confirmed_at')->nullable()->after('archive_requested_by');
            }
            if (!Schema::hasColumn('contracts', 'archive_confirmed_by')) {
                $table->foreignId('archive_confirmed_by')->nullable()->after('archive_confirmed_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('contracts', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('archive_confirmed_by');
            }
            if (!Schema::hasColumn('contracts', 'archived_by')) {
                $table->foreignId('archived_by')->nullable()->after('archived_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('contracts', 'archive_note')) {
                $table->text('archive_note')->nullable()->after('archived_by');
            }
            if (!Schema::hasColumn('contracts', 'boards_removed_confirmed_at')) {
                $table->timestamp('boards_removed_confirmed_at')->nullable()->after('archive_note');
            }
            if (!Schema::hasColumn('contracts', 'boards_removed_confirmed_by')) {
                $table->foreignId('boards_removed_confirmed_by')->nullable()->after('boards_removed_confirmed_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed', 'pending_archive', 'archived') DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        DB::table('contracts')
            ->whereIn('status', ['pending_archive', 'archived'])
            ->update(['status' => 'completed']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE contracts MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending'"
            );
        }

        Schema::table('contracts', function (Blueprint $table) {
            foreach ([
                'boards_removed_confirmed_by',
                'archived_by',
                'archive_confirmed_by',
                'archive_requested_by',
            ] as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropForeign([$column]);
                }
            }

            $columns = array_filter([
                Schema::hasColumn('contracts', 'archive_requested_at') ? 'archive_requested_at' : null,
                Schema::hasColumn('contracts', 'archive_requested_by') ? 'archive_requested_by' : null,
                Schema::hasColumn('contracts', 'archive_confirmed_at') ? 'archive_confirmed_at' : null,
                Schema::hasColumn('contracts', 'archive_confirmed_by') ? 'archive_confirmed_by' : null,
                Schema::hasColumn('contracts', 'archived_at') ? 'archived_at' : null,
                Schema::hasColumn('contracts', 'archived_by') ? 'archived_by' : null,
                Schema::hasColumn('contracts', 'archive_note') ? 'archive_note' : null,
                Schema::hasColumn('contracts', 'boards_removed_confirmed_at') ? 'boards_removed_confirmed_at' : null,
                Schema::hasColumn('contracts', 'boards_removed_confirmed_by') ? 'boards_removed_confirmed_by' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
