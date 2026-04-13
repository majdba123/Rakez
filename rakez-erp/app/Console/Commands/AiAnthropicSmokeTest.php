<?php

namespace App\Console\Commands;

use App\Services\AI\AiTextClientManager;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Console\Command;

class AiAnthropicSmokeTest extends Command
{
    protected $signature = 'ai:anthropic-smoke-test {--message=Hello from Laravel backend}';

    protected $description = 'Run a real Anthropic Messages API smoke test (requires ANTHROPIC_API_KEY).';

    public function handle(AiTextClientManager $clientManager): int
    {
        if (! config('ai_assistant.enabled')) {
            $this->error('AI assistant is disabled.');

            return self::FAILURE;
        }

        if (! config('anthropic.enabled', false)) {
            $this->error('Anthropic provider is disabled. Set ANTHROPIC_ENABLED=true in .env.');

            return self::FAILURE;
        }

        try {
            $response = $clientManager->createResponse(
                'You are a smoke test responder. Reply with a short confirmation.',
                [
                    ['role' => 'user', 'content' => (string) $this->option('message')],
                ],
                [
                    'section' => 'general',
                    'session_id' => 'anthropic-smoke-test',
                    'service' => 'ai.anthropic.smoke',
                ],
                [],
                'anthropic'
            );
        } catch (AiAssistantException $exception) {
            $this->error(sprintf(
                'Anthropic smoke test failed: %s (%s)',
                $exception->getMessage(),
                $exception->errorCode()
            ));

            return self::FAILURE;
        }

        $text = trim($response->text);
        $this->info('Response: ' . ($text !== '' ? $text : '[empty]'));

        return $text !== '' ? self::SUCCESS : self::FAILURE;
    }
}
