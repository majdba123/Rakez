<?php

namespace App\Services\AI\Realtime;

use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RealtimeGateway
{
    /**
     * @return array<string, mixed>
     */
    public function createClientSecretProbe(): array
    {
        $this->assertConfigured();

        $payload = [
            'expires_after' => [
                'anchor' => 'created_at',
                'seconds' => 600,
            ],
            'session' => [
                'type' => 'realtime',
                'model' => (string) config('ai_realtime.openai.model'),
                'instructions' => 'Realtime backend connectivity probe. Do not expose secrets.',
                'output_modalities' => ['audio'],
                'audio' => [
                    'output' => [
                        'voice' => (string) config('ai_realtime.openai.voice', 'marin'),
                    ],
                ],
            ],
        ];

        $start = microtime(true);
        $response = Http::withToken((string) config('openai.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai_assistant.openai.request_timeout', 30))
            ->post((string) config('ai_realtime.openai.client_secrets_endpoint'), $payload);

        if ($response->failed()) {
            Log::warning('OpenAI realtime client-secret probe failed', [
                'status' => $response->status(),
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);

            throw new AiAssistantException(
                'Realtime provider is unavailable or rejected the probe (status '.$response->status().').',
                'ai_realtime_provider_unavailable',
                $response->status() >= 400 ? $response->status() : 503
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new AiAssistantException('Realtime provider returned an invalid response.', 'ai_realtime_provider_unavailable', 503);
        }

        Log::info('OpenAI realtime client-secret probe ok', [
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            'model' => config('ai_realtime.openai.model'),
            'probe_id' => $json['id'] ?? null,
        ]);

        return [
            'probe_id' => $json['id'] ?? ($json['session']['id'] ?? (string) Str::uuid()),
            'expires_at' => $json['expires_at'] ?? null,
            'session_id' => $json['session']['id'] ?? null,
            'transport_supported' => 'provider_session_bootstrap_only',
        ];
    }

    public function assertConfigured(): void
    {
        $apiKey = config('openai.api_key');
        $model = config('ai_realtime.openai.model');

        if (! is_string($apiKey) || trim($apiKey) === '' || trim($apiKey) === 'test-fake-key-not-used') {
            throw new AiAssistantException('Realtime provider is not configured for this environment.', 'ai_realtime_provider_misconfigured', 503);
        }

        if (! is_string($model) || trim($model) === '') {
            throw new AiAssistantException('Realtime provider model configuration is incomplete.', 'ai_realtime_provider_misconfigured', 503);
        }
    }
}
