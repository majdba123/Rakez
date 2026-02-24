<?php

namespace App\Domain\Ads\Ports;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\ValueObjects\Platform;

interface AdsWritePort
{
    public function platform(): Platform;

    /**
     * Send a single outcome event to the platform's Conversions/Events API.
     * Returns the platform's response or confirmation identifier.
     */
    public function sendEvent(OutcomeEvent $event): array;

    /**
     * Send a batch of outcome events.
     *
     * @param  OutcomeEvent[]  $events
     * @return array  Per-event results
     */
    public function sendEventBatch(array $events): array;

    /**
     * Validate an event payload without actually sending it (if the platform supports it).
     */
    public function validateEvent(OutcomeEvent $event): array;
}
