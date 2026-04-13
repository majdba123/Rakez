<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Data\AiTextResponse;
use Generator;

interface AiTextProvider
{
    public function provider(): string;

    public function defaultModel(): string;

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function createResponse(string $instructions, array $messages, array $metadata = [], array $options = []): AiTextResponse;

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     * @return Generator<int, string>
     */
    public function createStreamedResponse(string $instructions, array $messages, array $metadata = [], array $options = []): Generator;
}
