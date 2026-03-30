<?php

namespace App\Events\AI;

use Illuminate\Foundation\Events\Dispatchable;

class AiRequestFailed
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly ?string $sessionId,
        public readonly string $error,
        public readonly int $attempts,
        public readonly ?string $correlationId = null,
    ) {}
}
