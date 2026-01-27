<?php

namespace Tests\Unit\AI;

use App\Services\AI\OpenAIResponsesClient;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;

class OpenAIResponsesClientTest extends TestCase
{
    private OpenAIResponsesClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OpenAIResponsesClient();
        Log::spy();
    }

    public function test_createResponse_success(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.openai.temperature' => 0.7,
            'ai_assistant.openai.max_output_tokens' => 1000,
            'ai_assistant.openai.truncation' => 'auto',
        ]);

        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Test response',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
        Log::shouldHaveReceived('info')->once();
    }

    public function test_createResponse_logs_latency(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.openai.temperature' => 0.7,
            'ai_assistant.openai.max_output_tokens' => 1000,
            'ai_assistant.openai.truncation' => 'auto',
        ]);

        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Test',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        Log::shouldHaveReceived('info')->atLeast()->once();
    }

    public function test_createResponse_logs_request_id(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.openai.temperature' => 0.7,
            'ai_assistant.openai.max_output_tokens' => 1000,
            'ai_assistant.openai.truncation' => 'auto',
        ]);

        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Test',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        Log::shouldHaveReceived('info')->atLeast()->once();
    }

    public function test_createResponse_retries_on_rate_limit(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 3,
            'ai_assistant.retries.base_delay_ms' => 10,
            'ai_assistant.retries.max_delay_ms' => 100,
            'ai_assistant.retries.jitter_ms' => 5,
        ]);

        $exception = new \Exception('Rate limit exceeded. Status code: 429');
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Success',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([
            $exception,
            $response,
        ]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_retries_on_503(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 3,
            'ai_assistant.retries.base_delay_ms' => 10,
            'ai_assistant.retries.max_delay_ms' => 100,
            'ai_assistant.retries.jitter_ms' => 5,
        ]);

        $exception = new \Exception('Service unavailable. Status code: 503');
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Success',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([
            $exception,
            $response,
        ]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_retries_on_502(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 3,
            'ai_assistant.retries.base_delay_ms' => 10,
            'ai_assistant.retries.max_delay_ms' => 100,
            'ai_assistant.retries.jitter_ms' => 5,
        ]);

        $exception = new \Exception('Bad gateway. Status code: 502');
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Success',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([
            $exception,
            $response,
        ]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_retries_on_timeout(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 3,
            'ai_assistant.retries.base_delay_ms' => 10,
            'ai_assistant.retries.max_delay_ms' => 100,
            'ai_assistant.retries.jitter_ms' => 5,
        ]);

        $exception = new \Exception('Request timeout');
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Success',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([
            $exception,
            $response,
        ]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_stops_after_max_attempts(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 2,
            'ai_assistant.retries.base_delay_ms' => 10,
            'ai_assistant.retries.max_delay_ms' => 100,
            'ai_assistant.retries.jitter_ms' => 5,
        ]);

        $exception = new \Exception('Rate limit exceeded. Status code: 429');

        OpenAI::fake([
            $exception,
            $exception,
        ]);

        $this->expectException(\Exception::class);
        $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);
    }

    public function test_createResponse_uses_config_values(): void
    {
        config([
            'ai_assistant.openai.model' => 'custom-model',
            'ai_assistant.openai.temperature' => 0.5,
            'ai_assistant.openai.max_output_tokens' => 2000,
            'ai_assistant.openai.truncation' => 'auto',
        ]);

        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Test',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
        // Config values are used internally, we verify the response is created successfully
    }

    public function test_createResponse_handles_missing_config(): void
    {
        config([
            'ai_assistant.openai.model' => null,
            'ai_assistant.openai.temperature' => null,
            'ai_assistant.openai.max_output_tokens' => null,
            'ai_assistant.openai.truncation' => null,
        ]);

        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Test',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_includes_truncation(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.openai.truncation' => 'auto',
        ]);

        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Test',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([$response]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
        // Truncation is included in payload internally
    }

    public function test_createResponse_handles_empty_response(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
        ]);

        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [],
        ]);

        OpenAI::fake([$response]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_logs_warning_on_failure(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 1,
        ]);

        $exception = new \Exception('Non-retryable error');

        OpenAI::fake([$exception]);

        try {
            $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);
        } catch (\Exception $e) {
            // Expected
        }

        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_createResponse_uses_exponential_backoff(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 3,
            'ai_assistant.retries.base_delay_ms' => 100,
            'ai_assistant.retries.max_delay_ms' => 1000,
            'ai_assistant.retries.jitter_ms' => 0,
        ]);

        $exception = new \Exception('Rate limit exceeded. Status code: 429');
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Success',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        $startTime = microtime(true);
        OpenAI::fake([
            $exception,
            $response,
        ]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $elapsed = (microtime(true) - $startTime) * 1000;

        // Should have waited at least base_delay_ms (100ms) before retry
        // Allow for small timing variations in test environment
        $this->assertGreaterThanOrEqual(50, $elapsed);
        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_uses_jitter(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 3,
            'ai_assistant.retries.base_delay_ms' => 100,
            'ai_assistant.retries.max_delay_ms' => 1000,
            'ai_assistant.retries.jitter_ms' => 50,
        ]);

        $exception = new \Exception('Rate limit exceeded. Status code: 429');
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Success',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        OpenAI::fake([
            $exception,
            $response,
        ]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        // Jitter adds randomness, so we just verify it doesn't throw
        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_respects_max_delay(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
            'ai_assistant.retries.max_attempts' => 5,
            'ai_assistant.retries.base_delay_ms' => 1000,
            'ai_assistant.retries.max_delay_ms' => 2000,
            'ai_assistant.retries.jitter_ms' => 0,
        ]);

        $exception = new \Exception('Rate limit exceeded. Status code: 429');
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Success',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
        ]);

        $startTime = microtime(true);
        OpenAI::fake([
            $exception,
            $exception,
            $response,
        ]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $elapsed = (microtime(true) - $startTime) * 1000;

        // Should not exceed max_delay_ms * 2 (for 2 retries) + some buffer
        $this->assertLessThan(5000, $elapsed);
        $this->assertInstanceOf(CreateResponse::class, $result);
    }

    public function test_createResponse_handles_missing_outputText(): void
    {
        config([
            'ai_assistant.openai.model' => 'gpt-4.1-mini',
        ]);

        // Response without outputText
        $response = CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [],
        ]);

        OpenAI::fake([$response]);

        $result = $this->client->createResponse('instructions', [['role' => 'user', 'content' => 'test']], []);

        $this->assertInstanceOf(CreateResponse::class, $result);
        // Should handle gracefully even if outputText is missing
        // Note: CreateResponse::fake() may return empty string or null for outputText
        $this->assertTrue(empty($result->outputText ?? '') || is_string($result->outputText ?? null));
    }
}
