<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\AiAssistantException;

class AiProviderResolver
{
    /**
     * @return list<string>
     */
    public function allowedProviders(): array
    {
        return ['openai', 'anthropic'];
    }

    public function resolve(?string $requestedProvider = null): string
    {
        $provider = strtolower(trim((string) ($requestedProvider ?: config('ai_assistant.default_provider', 'openai'))));

        if (! in_array($provider, $this->allowedProviders(), true)) {
            throw new AiAssistantException('AI provider selection is invalid.', 'ai_validation_failed', 422);
        }

        if ($provider === 'anthropic' && ! config('anthropic.enabled', false)) {
            throw new AiAssistantException(
                'AI provider is not configured for this environment.',
                'ai_provider_misconfigured',
                503
            );
        }

        return $provider;
    }
}
