<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Config;

/**
 * Skips tests unless OPENAI_API_KEY and AI_REAL_TESTS=true are set in .env (see ReadsDotEnvForTest).
 */
trait TestsWithRealOpenAiConnection
{
    protected string $realApiKey = '';

    protected function bootRealOpenAiFromDotEnv(): void
    {
        $envKey = $this->envFromDotFile('OPENAI_API_KEY');

        if (! $envKey || $envKey === 'test-fake-key-not-used') {
            $this->markTestSkipped('Real OPENAI_API_KEY not configured in .env');
        }

        if (! $this->envFromDotFileIsTrue('AI_REAL_TESTS')) {
            $this->markTestSkipped('AI_REAL_TESTS is not enabled — set AI_REAL_TESTS=true in .env to run');
        }

        $this->realApiKey = $envKey;

        Config::set('openai.api_key', $this->realApiKey);
        Config::set('ai_assistant.enabled', true);
        Config::set('ai_assistant.budgets.per_user_daily_tokens', 0);
        Config::set('ai_assistant.openai.max_output_tokens', 200);
        Config::set('ai_assistant.v2.openai.max_output_tokens', 400);
        Config::set('ai_assistant.retries.max_attempts', 2);
        Config::set('ai_assistant.retries.base_delay_ms', 200);

        app()->forgetInstance('openai');
        app()->forgetInstance(\OpenAI\Client::class);

        try {
            $client = \OpenAI::client($this->realApiKey);
            $client->models()->list();
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            if (str_contains($e->getMessage(), 'Country') || str_contains($e->getMessage(), 'region') || str_contains($e->getMessage(), 'territory')) {
                $this->markTestSkipped('OpenAI API is not available in this region: '.$e->getMessage());
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->markTestSkipped('OpenAI API connectivity issue: '.$e->getMessage());
        }
    }
}
