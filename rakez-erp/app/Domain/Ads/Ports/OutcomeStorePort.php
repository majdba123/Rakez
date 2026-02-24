<?php

namespace App\Domain\Ads\Ports;

use App\Domain\Ads\Entities\OutcomeEvent;

interface OutcomeStorePort
{
    /**
     * Enqueue an outcome event for delivery. Stored in the outbox table.
     */
    public function enqueue(OutcomeEvent $event): void;

    /**
     * Fetch pending (unsent) outcome events, ordered by creation time.
     *
     * @return OutcomeEvent[]
     */
    public function fetchPending(int $limit = 50): array;

    /**
     * Mark an event as successfully delivered.
     */
    public function markDelivered(string $eventId, string $platform, array $response = []): void;

    /**
     * Mark an event as failed, incrementing retry count.
     */
    public function markFailed(string $eventId, string $platform, string $error): void;

    /**
     * Move events that exceeded max retries to dead-letter status.
     */
    public function moveToDeadLetter(int $maxRetries = 5): int;
}
