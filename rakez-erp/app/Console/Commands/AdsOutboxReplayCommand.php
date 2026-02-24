<?php

namespace App\Console\Commands;

use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use App\Jobs\Ads\PublishOutcomeEventsJob;
use Illuminate\Console\Command;

class AdsOutboxReplayCommand extends Command
{
    protected $signature = 'ads:outbox
        {action : status|replay-failed|replay-dead-letter|purge-delivered}
        {--platform= : Filter by platform}
        {--limit=100 : Max events to process}
        {--days=30 : Days to keep for purge}';

    protected $description = 'Manage the Ads outcome events outbox (status, replay, purge)';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'status' => $this->showStatus(),
            'replay-failed' => $this->replayFailed(),
            'replay-dead-letter' => $this->replayDeadLetter(),
            'purge-delivered' => $this->purgeDelivered(),
            default => $this->error("Unknown action: {$this->argument('action')}") ?? self::FAILURE,
        };
    }

    private function showStatus(): int
    {
        $query = AdsOutcomeEvent::query();
        if ($platform = $this->option('platform')) {
            $query->where('platform', $platform);
        }

        $stats = $query->selectRaw('
            platform,
            status,
            COUNT(*) as count,
            AVG(retry_count) as avg_retries,
            MAX(retry_count) as max_retries
        ')
            ->groupBy('platform', 'status')
            ->get();

        $this->table(
            ['Platform', 'Status', 'Count', 'Avg Retries', 'Max Retries'],
            $stats->map(fn ($s) => [
                $s->platform,
                $s->status,
                $s->count,
                round($s->avg_retries, 1),
                $s->max_retries,
            ])->toArray(),
        );

        return self::SUCCESS;
    }

    private function replayFailed(): int
    {
        $query = AdsOutcomeEvent::where('status', 'pending')
            ->where('retry_count', '>', 0);

        if ($platform = $this->option('platform')) {
            $query->where('platform', $platform);
        }

        $count = $query->limit((int) $this->option('limit'))
            ->update([
                'retry_count' => 0,
                'last_error' => null,
                'status' => 'pending',
            ]);

        $this->info("Reset {$count} failed events for replay.");

        PublishOutcomeEventsJob::dispatch((int) $this->option('limit'));

        return self::SUCCESS;
    }

    private function replayDeadLetter(): int
    {
        $query = AdsOutcomeEvent::where('status', 'dead_letter');

        if ($platform = $this->option('platform')) {
            $query->where('platform', $platform);
        }

        $count = $query->limit((int) $this->option('limit'))
            ->update([
                'retry_count' => 0,
                'status' => 'pending',
                'last_error' => null,
            ]);

        $this->info("Moved {$count} dead-letter events back to pending.");

        PublishOutcomeEventsJob::dispatch((int) $this->option('limit'));

        return self::SUCCESS;
    }

    private function purgeDelivered(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $query = AdsOutcomeEvent::where('status', 'delivered')
            ->where('updated_at', '<', $cutoff);

        if ($platform = $this->option('platform')) {
            $query->where('platform', $platform);
        }

        $count = $query->delete();

        $this->info("Purged {$count} delivered events older than {$days} days.");

        return self::SUCCESS;
    }
}
