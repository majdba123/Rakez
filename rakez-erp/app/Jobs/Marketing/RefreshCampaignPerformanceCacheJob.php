<?php

namespace App\Jobs\Marketing;

use App\Services\Marketing\AI\CampaignPerformanceAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshCampaignPerformanceCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function handle(CampaignPerformanceAggregator $aggregator): void
    {
        Log::channel('ads')->info('RefreshCampaignPerformanceCache: Starting');

        $last30 = $aggregator->byPlatform(
            now()->subDays(30)->toDateString(),
            now()->toDateString(),
        );

        Cache::put('ads:platform_performance:30d', $last30->map->toArray()->toArray(), now()->addHours(6));

        $last7 = $aggregator->byPlatform(
            now()->subDays(7)->toDateString(),
            now()->toDateString(),
        );

        Cache::put('ads:platform_performance:7d', $last7->map->toArray()->toArray(), now()->addHours(6));

        $benchmarks = $aggregator->benchmarkAgainstGuardrails(
            now()->subDays(30)->toDateString(),
            now()->toDateString(),
        );

        Cache::put('ads:benchmarks:30d', $benchmarks, now()->addHours(6));

        Log::channel('ads')->info('RefreshCampaignPerformanceCache: Completed', [
            'platforms_cached' => $last30->count(),
        ]);
    }
}
