<?php

namespace Tests\Unit\AI;

use App\Services\AI\Anthropic\AnthropicCredentialResolver;
use App\Services\AI\Exceptions\AiAssistantException;
use Tests\TestCase;

class AnthropicCredentialResolverTest extends TestCase
{
    public function test_it_uses_env_key_when_override_is_disabled(): void
    {
        config([
            'anthropic.allow_user_override' => false,
            'anthropic.api_key' => 'sk-ant-env-default',
        ]);

        $resolved = app(AnthropicCredentialResolver::class)->resolve('sk-ant-user-override');

        $this->assertSame('sk-ant-env-default', $resolved['api_key']);
        $this->assertSame('env_default', $resolved['source']);
    }

    public function test_it_uses_override_when_enabled(): void
    {
        config([
            'anthropic.allow_user_override' => true,
            'anthropic.api_key' => 'sk-ant-env-default',
        ]);

        $resolved = app(AnthropicCredentialResolver::class)->resolve('sk-ant-user-override');

        $this->assertSame('sk-ant-user-override', $resolved['api_key']);
        $this->assertSame('user_override', $resolved['source']);
    }

    public function test_it_rejects_invalid_override_safely(): void
    {
        config([
            'anthropic.allow_user_override' => true,
            'anthropic.api_key' => 'sk-ant-env-default',
        ]);

        try {
            app(AnthropicCredentialResolver::class)->resolve('plain-text-secret');
            $this->fail('Expected invalid override to be rejected.');
        } catch (AiAssistantException $exception) {
            $this->assertSame('ai_provider_misconfigured', $exception->errorCode());
            $this->assertStringNotContainsString('plain-text-secret', $exception->getMessage());
        }
    }

    public function test_it_fails_fast_when_no_valid_env_key_exists(): void
    {
        config([
            'anthropic.allow_user_override' => false,
            'anthropic.api_key' => '',
        ]);

        $this->expectException(AiAssistantException::class);
        $this->expectExceptionMessage('AI provider is not configured for this environment.');

        app(AnthropicCredentialResolver::class)->resolve();
    }
}
