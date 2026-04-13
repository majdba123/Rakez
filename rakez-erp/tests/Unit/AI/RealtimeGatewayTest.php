<?php

namespace Tests\Unit\AI;

use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealtimeGatewayTest extends TestCase
{
    public function test_probe_fails_fast_when_realtime_provider_is_misconfigured(): void
    {
        config([
            'openai.api_key' => '',
            'ai_realtime.openai.model' => 'gpt-realtime',
        ]);

        Http::fake();

        $gateway = app(RealtimeGateway::class);

        try {
            $gateway->createClientSecretProbe();
            $this->fail('Expected realtime provider misconfiguration exception was not thrown.');
        } catch (AiAssistantException $exception) {
            $this->assertSame('ai_realtime_provider_misconfigured', $exception->errorCode());
            $this->assertSame(503, $exception->statusCode());
        }

        Http::assertNothingSent();
    }

    public function test_probe_returns_sanitized_bootstrap_metadata_without_secret_value(): void
    {
        config([
            'openai.api_key' => 'sk-test-configured',
            'ai_realtime.openai.model' => 'gpt-realtime',
        ]);

        Http::fake([
            '*' => Http::response([
                'id' => 'rt_probe_123',
                'expires_at' => 1234567890,
                'client_secret' => [
                    'value' => 'ephemeral-secret-should-not-leak',
                ],
                'session' => [
                    'id' => 'sess_123',
                ],
            ], 200),
        ]);

        $result = app(RealtimeGateway::class)->createClientSecretProbe();

        $this->assertSame('rt_probe_123', $result['probe_id']);
        $this->assertSame('sess_123', $result['session_id']);
        $this->assertArrayNotHasKey('client_secret', $result);
    }
}
