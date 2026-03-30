<?php

namespace App\Events\AI;

use Illuminate\Foundation\Events\Dispatchable;

class AiRequestCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly ?string $sessionId,
        public readonly ?string $section,
        public readonly string $requestType,
        public readonly string $model,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        public readonly float $latencyMs,
        public readonly int $toolCallsCount,
        public readonly ?string $correlationId = null,
    ) {}
}
