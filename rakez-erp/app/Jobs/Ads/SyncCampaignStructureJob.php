<?php

namespace App\Jobs\Ads;

use App\Application\Ads\SyncCampaignStructure;
use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\Ports\InsightStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCampaignStructureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $platform,
        public readonly string $accountId,
    ) {
        $this->queue = 'ads-sync';
    }

    public function handle(InsightStorePort $store): void
    {
        $reader = app(AdsReadPort::class . '.' . $this->platform);

        $useCase = new SyncCampaignStructure($reader, $store);
        $useCase->execute($this->accountId);

        Log::info("Synced campaign structure for {$this->platform}:{$this->accountId}");
    }
}
