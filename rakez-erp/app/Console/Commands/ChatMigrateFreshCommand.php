<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatMigrateFreshCommand extends Command
{
    protected $signature = 'chat:migrate-fresh
                            {--force : Skip confirmation (required with --no-interaction)}';

    protected $description = 'Drop conversations + messages tables and re-run only their migrations (chat data is lost)';

    private const MIGRATION_NAMES = [
        '2026_02_13_000001_create_conversations_table',
        '2026_02_13_000002_create_messages_table',
        '2026_04_14_000001_add_voice_fields_to_messages_table',
        '2026_04_15_000001_add_attachment_fields_to_messages_table',
    ];

    private const MIGRATION_PATHS = [
        'database/migrations/2026_02_13_000001_create_conversations_table.php',
        'database/migrations/2026_02_13_000002_create_messages_table.php',
        'database/migrations/2026_04_14_000001_add_voice_fields_to_messages_table.php',
        'database/migrations/2026_04_15_000001_add_attachment_fields_to_messages_table.php',
    ];

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('In production use: php artisan chat:migrate-fresh --force');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This deletes ALL rows in `messages` and `conversations`. Continue?', false)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $this->info('Dropping chat tables…');

        Schema::disableForeignKeyConstraints();
        try {
            Schema::dropIfExists('messages');
            Schema::dropIfExists('conversations');
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $deleted = DB::table('migrations')->whereIn('migration', self::MIGRATION_NAMES)->delete();
        $this->line("Removed {$deleted} migration row(s) from `migrations`.");

        foreach (self::MIGRATION_PATHS as $path) {
            $this->info("Migrating: {$path}");
            $code = $this->call('migrate', [
                '--path' => $path,
                '--no-interaction' => true,
            ]);
            if ($code !== 0) {
                $this->error("migrate failed for {$path}");

                return self::FAILURE;
            }
        }

        $this->info('Chat tables are fresh.');

        return self::SUCCESS;
    }
}
