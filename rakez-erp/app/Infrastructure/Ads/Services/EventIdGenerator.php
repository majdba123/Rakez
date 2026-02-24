<?php

namespace App\Infrastructure\Ads\Services;

use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;

final class EventIdGenerator
{
    /**
     * Generate a deterministic, idempotent event ID from the combination of
     * platform + customer identifier + outcome type + timestamp + optional order ID.
     * This ensures the same real-world event always produces the same event_id.
     */
    public function generate(
        Platform $platform,
        string $customerId,
        OutcomeType $outcomeType,
        \DateTimeInterface $occurredAt,
        ?string $orderId = null,
    ): string {
        $components = [
            $platform->value,
            $customerId,
            $outcomeType->value,
            $occurredAt->format('Y-m-d\TH:i:s'),
        ];

        if ($orderId !== null) {
            $components[] = $orderId;
        }

        return hash('sha256', implode('|', $components));
    }
}
