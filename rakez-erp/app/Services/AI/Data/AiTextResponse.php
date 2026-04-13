<?php

namespace App\Services\AI\Data;

final readonly class AiTextResponse
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $provider,
        public string $text,
        public string $model,
        public ?string $responseId = null,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $totalTokens = null,
        public ?int $latencyMs = null,
        public ?string $requestId = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {}
}
