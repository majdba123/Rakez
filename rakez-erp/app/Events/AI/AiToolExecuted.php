<?php

namespace App\Events\AI;

use Illuminate\Foundation\Events\Dispatchable;

class AiToolExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly string $toolName,
        public readonly float $durationMs,
        public readonly bool $denied,
        public readonly ?string $correlationId = null,
    ) {}
}
