<?php

namespace App\Events\AI;

use Illuminate\Foundation\Events\Dispatchable;

class AiDocumentIngested
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $documentId,
        public readonly string $title,
        public readonly int $chunksCount,
        public readonly int $totalTokens,
        public readonly ?string $correlationId = null,
    ) {}
}
