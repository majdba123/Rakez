<?php

namespace Tests\Traits;

use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;

/**
 * Trait for tests that interact with AI/OpenAI services
 * 
 * Provides reusable methods for mocking OpenAI responses and common AI test patterns
 */
trait TestsWithAI
{
    /**
     * Create a fake OpenAI response with the given text
     *
     * @param string $text The response text
     * @param string|null $id Optional response ID
     * @param array $usage Optional usage statistics
     * @return CreateResponse
     */
    protected function fakeAIResponse(
        string $text,
        ?string $id = null,
        array $usage = []
    ): CreateResponse {
        return CreateResponse::fake([
            'id' => $id ?? 'resp_' . uniqid(),
            'model' => 'gpt-4.1-mini',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_' . uniqid(),
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $text,
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
            'usage' => array_merge([
                'input_tokens' => 10,
                'output_tokens' => 5,
                'total_tokens' => 15,
            ], $usage),
        ]);
    }

    /**
     * Create multiple fake AI responses
     *
     * @param array $texts Array of response texts
     * @return array Array of CreateResponse objects
     */
    protected function fakeAIResponses(array $texts): array
    {
        return array_map(fn($text) => $this->fakeAIResponse($text), $texts);
    }

    /**
     * Mock OpenAI with a single response
     *
     * @param string $text The response text
     * @return void
     */
    protected function mockAIResponse(string $text): void
    {
        OpenAI::fake([
            $this->fakeAIResponse($text),
        ]);
    }

    /**
     * Mock OpenAI with multiple responses
     *
     * @param array $texts Array of response texts
     * @return void
     */
    protected function mockAIResponses(array $texts): void
    {
        OpenAI::fake($this->fakeAIResponses($texts));
    }

    /**
     * Mock OpenAI to throw an error
     *
     * @param string $errorMessage The error message
     * @return void
     */
    protected function mockAIError(string $errorMessage): void
    {
        OpenAI::fake([
            new \Exception($errorMessage),
        ]);
    }

    /**
     * Mock OpenAI with an empty response
     *
     * @return void
     */
    protected function mockAIEmptyResponse(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'id' => 'resp_empty',
                'output' => [],
            ]),
        ]);
    }

    /**
     * Mock OpenAI with a rate limit error
     *
     * @return void
     */
    protected function mockAIRateLimitError(): void
    {
        $this->mockAIError('429 Rate limit exceeded');
    }

    /**
     * Mock OpenAI with a timeout error
     *
     * @return void
     */
    protected function mockAITimeoutError(): void
    {
        $this->mockAIError('Request timeout');
    }

    /**
     * Mock OpenAI with a service unavailable error
     *
     * @return void
     */
    protected function mockAIServiceUnavailable(): void
    {
        $this->mockAIError('503 Service temporarily unavailable');
    }

    /**
     * Assert that OpenAI was called with specific parameters
     *
     * @param callable $callback Callback to validate parameters
     * @return void
     */
    protected function assertAICalled(callable $callback): void
    {
        OpenAI::assertSent(\OpenAI\Resources\Responses::class, $callback);
    }

    /**
     * Assert that OpenAI was called with instructions containing text
     *
     * @param string $text Text that should be in instructions
     * @return void
     */
    protected function assertAIInstructionsContain(string $text): void
    {
        $this->assertAICalled(function (string $method, array $parameters) use ($text) {
            return str_contains($parameters['instructions'] ?? '', $text);
        });
    }

    /**
     * Assert that OpenAI was called with a message containing text
     *
     * @param string $text Text that should be in messages
     * @return void
     */
    protected function assertAIMessageContains(string $text): void
    {
        $this->assertAICalled(function (string $method, array $parameters) use ($text) {
            $messages = $parameters['input'] ?? [];
            return collect($messages)->contains(fn($m) => str_contains($m['content'] ?? '', $text));
        });
    }

    /**
     * Set AI budget for testing
     *
     * @param int $tokens Token budget
     * @return void
     */
    protected function setAIBudget(int $tokens): void
    {
        config(['ai_assistant.budgets.per_user_daily_tokens' => $tokens]);
    }

    /**
     * Disable AI budget for testing
     *
     * @return void
     */
    protected function disableAIBudget(): void
    {
        $this->setAIBudget(0);
    }

    /**
     * Configure AI chat settings for testing
     *
     * @param int $summaryEvery Messages before creating summary
     * @param int $tailMessages Number of recent messages to keep
     * @param int $summaryWindow Messages to include in summary
     * @return void
     */
    protected function configureAIChat(
        int $summaryEvery = 12,
        int $tailMessages = 6,
        int $summaryWindow = 12
    ): void {
        config([
            'ai_assistant.chat.summary_every' => $summaryEvery,
            'ai_assistant.chat.tail_messages' => $tailMessages,
            'ai_assistant.chat.summary_window' => $summaryWindow,
        ]);
    }
}
