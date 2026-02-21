<?php

namespace App\Console\Commands;

use App\Models\AIConversation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeAiConversations extends Command
{
    protected $signature = 'ai:purge-conversations {--days= : Retention window in days}';
    protected $description = 'Purge AI conversations older than the retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('ai_assistant.retention.days', 90));

        if ($days <= 0) {
            $this->warn('Retention days must be greater than 0.');
            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = AIConversation::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} AI conversation rows older than {$days} days.");

        return self::SUCCESS;
    }
}
