<?php

namespace App\Console\Commands;

use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeTransportClient;
use Illuminate\Console\Command;

class AiRealtimeWebSocketSmokeTest extends Command
{
    protected $signature = 'ai:realtime-websocket-smoke-test';

    protected $description = 'Open a real backend WebSocket to OpenAI Realtime and wait for a first event.';

    public function handle(RealtimeTransportClient $client): int
    {
        $received = null;

        try {
            $client->run(
                onEvent: function (array $event) use (&$received): void {
                    $received = $event;
                },
                onOpen: function () use ($client): void {
                    $client->send([
                        'type' => 'session.update',
                        'session' => [
                            'type' => 'realtime',
                            'model' => (string) config('ai_realtime.openai.model'),
                            'instructions' => 'Realtime WebSocket smoke test.',
                            'output_modalities' => ['audio'],
                            'audio' => [
                                'output' => [
                                    'voice' => (string) config('ai_realtime.openai.voice', 'marin'),
                                ],
                            ],
                        ],
                    ]);
                },
                shouldStop: function () use (&$received): bool {
                    return is_array($received);
                },
                timeoutSeconds: 20,
            );
        } catch (AiAssistantException $exception) {
            $this->error($exception->errorCode().': '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_array($received)) {
            $this->error('No realtime event received.');

            return self::FAILURE;
        }

        $this->info('Realtime WebSocket smoke ok.');
        $this->line('First event type: '.($received['type'] ?? 'unknown'));
        $this->line('Session ID: '.($received['session']['id'] ?? 'n/a'));

        return self::SUCCESS;
    }
}
