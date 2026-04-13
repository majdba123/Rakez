<?php

namespace App\Console\Commands;

use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeTransportClient;
use Illuminate\Console\Command;

class AiRealtimeRoundtripSmokeTest extends Command
{
    protected $signature = 'ai:realtime-roundtrip-smoke-test';

    protected $description = 'Open a backend Realtime WebSocket, send a user turn, and wait for response.done.';

    public function handle(RealtimeTransportClient $client): int
    {
        $receivedTypes = [];
        $responseDone = false;
        $promptSent = false;
        $deadline = now()->addSeconds(20);
        $providerError = null;

        try {
            $client->run(
                onEvent: function (array $event) use (&$receivedTypes, &$responseDone, &$promptSent, &$providerError, $client): void {
                    $type = (string) ($event['type'] ?? 'unknown');
                    $receivedTypes[] = $type;

                    if ($type === 'error') {
                        $providerError = is_array($event['error'] ?? null) ? $event['error'] : $event;
                    }

                    if (! $promptSent && in_array($type, ['session.created', 'session.updated'], true)) {
                        $client->send([
                            'type' => 'conversation.item.create',
                            'item' => [
                                'type' => 'message',
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'input_text',
                                        'text' => 'Say hello in one short sentence.',
                                    ],
                                ],
                            ],
                        ]);

                        $client->send([
                            'type' => 'response.create',
                            'response' => [
                                'instructions' => 'Reply in one short sentence.',
                            ],
                        ]);

                        $promptSent = true;
                    }

                    if ($type === 'response.done') {
                        $responseDone = true;
                    }
                },
                onOpen: function () use ($client): void {
                    $client->send([
                        'type' => 'session.update',
                        'session' => [
                            'type' => 'realtime',
                            'model' => (string) config('ai_realtime.openai.model'),
                            'instructions' => 'Reply with one short English sentence only.',
                            'output_modalities' => ['text'],
                        ],
                    ]);
                },
                shouldStop: function () use (&$responseDone, $deadline): bool {
                    return $responseDone || now()->greaterThanOrEqualTo($deadline);
                },
                timeoutSeconds: 25,
            );
        } catch (AiAssistantException $exception) {
            $this->error($exception->errorCode().': '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $responseDone) {
            $this->error('Realtime roundtrip did not reach response.done.');
            $this->line('Observed events: '.implode(', ', array_slice($receivedTypes, 0, 20)));
            if (is_array($providerError)) {
                $this->line('Provider error: '.json_encode($providerError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::FAILURE;
        }

        $this->info('Realtime roundtrip smoke ok.');
        $this->line('Observed events: '.implode(', ', array_slice($receivedTypes, 0, 12)));

        return self::SUCCESS;
    }
}
