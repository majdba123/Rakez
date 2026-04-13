<?php

namespace App\Services\AI\Anthropic;

use App\Services\AI\Exceptions\AiAssistantException;

class AnthropicCredentialResolver
{
    /**
     * @return array{api_key: string, source: string}
     */
    public function resolve(?string $overrideKey = null): array
    {
        $allowOverride = (bool) config('anthropic.allow_user_override', false);

        if ($allowOverride && $overrideKey !== null) {
            $candidate = trim($overrideKey);
            if ($this->isValidApiKey($candidate)) {
                return [
                    'api_key' => $candidate,
                    'source' => 'user_override',
                ];
            }

            throw new AiAssistantException(
                'AI provider is not configured for this environment.',
                'ai_provider_misconfigured',
                503
            );
        }

        $apiKey = trim((string) config('anthropic.api_key', ''));
        if ($this->isValidApiKey($apiKey)) {
            return [
                'api_key' => $apiKey,
                'source' => 'env_default',
            ];
        }

        throw new AiAssistantException(
            'AI provider is not configured for this environment.',
            'ai_provider_misconfigured',
            503
        );
    }

    private function isValidApiKey(string $apiKey): bool
    {
        if ($apiKey === '' || $apiKey === 'test-fake-key-not-used') {
            return false;
        }

        return str_starts_with($apiKey, 'sk-ant-');
    }
}
