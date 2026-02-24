<?php

namespace App\Jobs\Ads;

use App\Application\Ads\PublishOutcomeEvents;
use App\Domain\Ads\Ports\AdsWritePort;
use App\Domain\Ads\Ports\OutcomeStorePort;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishOutcomeEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $batchSize = 50,
    ) {
        $this->queue = 'ads-publish';
    }

    public function handle(OutcomeStorePort $store): void
    {
        $writers = [];
        foreach (['meta', 'snap', 'tiktok'] as $platform) {
            $binding = AdsWritePort::class . '.' . $platform;
            if (app()->bound($binding)) {
                $writers[$platform] = app($binding);
            }
        }

        $useCase = new PublishOutcomeEvents($store, $writers);
        $processed = $useCase->execute($this->batchSize);

        Log::info("Published {$processed} outcome events");
    }
}
