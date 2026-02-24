<?php

namespace App\Application\Ads;

use App\Domain\Ads\Ports\AdsWritePort;
use App\Domain\Ads\Ports\OutcomeStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use Illuminate\Support\Facades\Log;

final class PublishOutcomeEvents
{
    /**
     * @param  array<string, AdsWritePort>  $writers  Keyed by platform value
     */
    public function __construct(
        private readonly OutcomeStorePort $store,
        private readonly array $writers,
    ) {}

    public function execute(int $batchSize = 50): int
    {
        $this->store->moveToDeadLetter();

        $events = $this->store->fetchPending($batchSize);
        $processed = 0;

        foreach ($events as $event) {
            foreach ($event->targetPlatforms as $platform) {
                $writer = $this->writers[$platform->value] ?? null;
                if (! $writer) {
                    Log::warning("No AdsWritePort registered for platform: {$platform->value}");
                    continue;
                }

                try {
                    $response = $writer->sendEvent($event);
                    $this->store->markDelivered($event->eventId, $platform->value, $response);
                    $processed++;
                } catch (\Throwable $e) {
                    Log::error("Failed to send event {$event->eventId} to {$platform->value}", [
                        'error' => $e->getMessage(),
                    ]);
                    $this->store->markFailed($event->eventId, $platform->value, $e->getMessage());
                }
            }
        }

        return $processed;
    }
}
