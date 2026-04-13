<?php

namespace App\Services\AI\SafeWrites;

final class SafeWriteIntentDecision
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly array $context = [],
    ) {}

    /**
     * @return array{evaluated: true, allowed: bool, reason: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'evaluated' => true,
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'context' => $this->context,
        ];
    }
}
