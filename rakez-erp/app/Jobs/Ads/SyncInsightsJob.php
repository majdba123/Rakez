<?php

namespace App\Jobs\Ads;

use App\Application\Ads\SyncInsights;
use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\Ports\InsightStorePort;
use App\Domain\Ads\ValueObjects\DateRange;
use App\Infrastructure\Ads\Persistence\Models\AdsSyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    /**
     * @param  string[]  $levels
     */
    public function __construct(
        public readonly string $platform,
        public readonly string $accountId,
        public readonly string $dateStart,
        public readonly string $dateEnd,
        public readonly array $levels = ['campaign'],
    ) {
        $this->queue = 'ads-sync';
    }

    public function handle(InsightStorePort $store): void
    {
        $run = AdsSyncRun::create([
            'type' => 'insights',
            'platform' => $this->platform,
            'account_id' => $this->accountId,
            'status' => 'running',
            'started_at' => now(),
            'meta' => [
                'date_start' => $this->dateStart,
                'date_end' => $this->dateEnd,
                'levels' => $this->levels,
            ],
        ]);

        try {
            $reader = app(AdsReadPort::class . '.' . $this->platform);

            $dateRange = new DateRange(
                \Carbon\CarbonImmutable::parse($this->dateStart),
                \Carbon\CarbonImmutable::parse($this->dateEnd),
            );

            $useCase = new SyncInsights($reader, $store);
            $useCase->execute($this->accountId, $dateRange, $this->levels);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);

            Log::info("Synced insights for {$this->platform}:{$this->accountId}", [
                'date_range' => "{$this->dateStart} -> {$this->dateEnd}",
                'levels' => $this->levels,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
