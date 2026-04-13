<?php

namespace App\Services\AI;

use App\Services\AI\Anthropic\AnthropicTextProvider;
use App\Services\AI\Contracts\AiTextProvider;
use App\Services\AI\Data\AiTextResponse;
use Generator;

class AiTextClientManager
{
    public function __construct(
        private readonly AiProviderResolver $providerResolver,
        private readonly OpenAiTextProvider $openAiProvider,
        private readonly AnthropicTextProvider $anthropicProvider,
    ) {}

    public function resolveProvider(?string $requestedProvider = null): string
    {
        return $this->providerResolver->resolve($requestedProvider);
    }

    public function defaultModelFor(?string $requestedProvider = null): string
    {
        return $this->provider($requestedProvider)->defaultModel();
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function createResponse(
        string $instructions,
        array $messages,
        array $metadata = [],
        array $options = [],
        ?string $requestedProvider = null,
    ): AiTextResponse {
        return $this->provider($requestedProvider)->createResponse($instructions, $messages, $metadata, $options);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     * @return Generator<int, string>
     */
    public function createStreamedResponse(
        string $instructions,
        array $messages,
        array $metadata = [],
        array $options = [],
        ?string $requestedProvider = null,
    ): Generator {
        yield from $this->provider($requestedProvider)->createStreamedResponse($instructions, $messages, $metadata, $options);
    }

    private function provider(?string $requestedProvider = null): AiTextProvider
    {
        return match ($this->providerResolver->resolve($requestedProvider)) {
            'anthropic' => $this->anthropicProvider,
            default => $this->openAiProvider,
        };
    }
}
