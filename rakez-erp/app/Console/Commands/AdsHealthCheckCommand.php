<?php

namespace App\Console\Commands;

use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use App\Infrastructure\Ads\Persistence\Models\AdsExport;
use App\Infrastructure\Ads\Persistence\Models\AdsLeadSubmission;
use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use App\Infrastructure\Ads\Persistence\Models\AdsSyncRun;
use Illuminate\Console\Command;

class AdsHealthCheckCommand extends Command
{
    protected $signature = 'ads:health';

    protected $description = 'Display Ads integration health metrics';

    public function handle(): int
    {
        $this->info('=== Platform Accounts ===');
        $accounts = AdsPlatformAccount::where('is_active', true)->get();
        $this->table(
            ['Platform', 'Account ID', 'Token Expires', 'Active'],
            $accounts->map(fn ($a) => [
                $a->platform,
                $a->account_id,
                $a->token_expires_at?->diffForHumans() ?? 'Never',
                $a->is_active ? 'Yes' : 'No',
            ])->toArray(),
        );

        $this->newLine();
        $this->info('=== Insights (last 24h) ===');
        $recentInsights = AdsInsightRow::where('updated_at', '>=', now()->subDay())
            ->selectRaw('platform, level, COUNT(*) as rows, SUM(impressions) as total_impressions, SUM(spend) as total_spend')
            ->groupBy('platform', 'level')
            ->get();

        if ($recentInsights->isEmpty()) {
            $this->warn('No insights synced in the last 24 hours.');
        } else {
            $this->table(
                ['Platform', 'Level', 'Rows', 'Impressions', 'Spend'],
                $recentInsights->map(fn ($r) => [
                    $r->platform,
                    $r->level,
                    $r->rows,
                    number_format($r->total_impressions),
                    number_format($r->total_spend, 2),
                ])->toArray(),
            );
        }

        $this->newLine();
        $this->info('=== Lead Submissions (last 24h) ===');
        $recentLeads = AdsLeadSubmission::where('synced_at', '>=', now()->subDay())
            ->selectRaw('platform, account_id, COUNT(*) as rows')
            ->groupBy('platform', 'account_id')
            ->get();

        if ($recentLeads->isEmpty()) {
            $this->warn('No leads synced in the last 24 hours.');
        } else {
            $this->table(
                ['Platform', 'Account ID', 'Rows'],
                $recentLeads->map(fn ($r) => [
                    $r->platform,
                    $r->account_id,
                    $r->rows,
                ])->toArray(),
            );
        }

        $this->newLine();
        $this->info('=== Exports (last 24h) ===');
        $recentExports = AdsExport::where('created_at', '>=', now()->subDay())
            ->selectRaw('type, status, COUNT(*) as count')
            ->groupBy('type', 'status')
            ->get();

        if ($recentExports->isEmpty()) {
            $this->warn('No exports created in the last 24 hours.');
        } else {
            $this->table(
                ['Type', 'Status', 'Count'],
                $recentExports->map(fn ($r) => [$r->type, $r->status, $r->count])->toArray(),
            );
        }

        $this->newLine();
        $this->info('=== Sync Runs (last 24h) ===');
        $runs = AdsSyncRun::where('started_at', '>=', now()->subDay())
            ->selectRaw('type, status, COUNT(*) as count')
            ->groupBy('type', 'status')
            ->get();

        if ($runs->isEmpty()) {
            $this->warn('No sync runs recorded in the last 24 hours.');
        } else {
            $this->table(
                ['Type', 'Status', 'Count'],
                $runs->map(fn ($r) => [$r->type, $r->status, $r->count])->toArray(),
            );
        }

        $this->newLine();
        $this->info('=== Outcome Events (last 24h) ===');
        $recentOutcomes = AdsOutcomeEvent::where('created_at', '>=', now()->subDay())
            ->selectRaw('platform, status, COUNT(*) as count')
            ->groupBy('platform', 'status')
            ->get();

        if ($recentOutcomes->isEmpty()) {
            $this->warn('No outcome events in the last 24 hours.');
        } else {
            $this->table(
                ['Platform', 'Status', 'Count'],
                $recentOutcomes->map(fn ($o) => [
                    $o->platform,
                    $o->status,
                    $o->count,
                ])->toArray(),
            );
        }

        $deadLetterCount = AdsOutcomeEvent::where('status', 'dead_letter')->count();
        if ($deadLetterCount > 0) {
            $this->error("Dead-letter queue has {$deadLetterCount} events. Run: php artisan ads:outbox replay-dead-letter");
        }

        $expiringSoon = AdsPlatformAccount::where('is_active', true)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addDays(3))
            ->orderBy('token_expires_at')
            ->get(['platform', 'account_id', 'token_expires_at']);

        if ($expiringSoon->isNotEmpty()) {
            $this->newLine();
            $this->warn('=== Tokens Expiring Soon (<= 3 days) ===');
            $this->table(
                ['Platform', 'Account ID', 'Expires'],
                $expiringSoon->map(fn ($a) => [
                    $a->platform,
                    $a->account_id,
                    $a->token_expires_at?->toDateTimeString(),
                ])->toArray(),
            );
        }

        return self::SUCCESS;
    }
}
