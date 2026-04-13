<?php

namespace App\Console\Commands;

use App\Services\AI\OpenAIResponsesClient;
use Illuminate\Console\Command;

class AiSmokeTest extends Command
{
    protected $signature = 'ai:smoke-test {--message=Hello from Rakez ERP}';
    protected $description = 'Run a real OpenAI Responses API smoke test (requires OPENAI_API_KEY).';

    public function handle(OpenAIResponsesClient $client): int
    {
        if (! config('ai_assistant.enabled')) {
            $this->error('AI assistant is disabled.');
            return self::FAILURE;
        }

        $apiKey = config('openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '' || trim($apiKey) === 'test-fake-key-not-used') {
            $this->error('OpenAI provider is not configured. Set OPENAI_API_KEY in .env.');
            return self::FAILURE;
        }

        $message = (string) $this->option('message');

        $response = $client->createResponse(
            'You are a smoke test responder. Reply with a short confirmation.',
            [
                ['role' => 'user', 'content' => $message],
            ],
            ['section' => 'general', 'session_id' => 'smoke-test']
        );

        $text = $response->outputText ?? '';
        $this->info('Response: ' . ($text !== '' ? $text : '[empty]'));

        return $text !== '' ? self::SUCCESS : self::FAILURE;
    }
}
