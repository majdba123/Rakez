<?php

namespace App\Console\Commands;

use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeTransportClient;
use Illuminate\Console\Command;

class AiRealtimeAudioBufferSmokeTest extends Command
{
    protected $signature = 'ai:realtime-audio-buffer-smoke-test';

    protected $description = 'Send input_audio_buffer.append and commit over a live backend Realtime socket.';

    public function handle(RealtimeTransportClient $client): int
    {
        $receivedTypes = [];
        $providerError = null;
        $responseDone = false;
        $sessionReady = false;
        $audioAppended = false;
        $audioCommitted = false;
        $responseRequested = false;
        $deadline = now()->addSeconds(20);
        $audioChunks = $this->buildPcm16ToneChunks();
        $sessionUpdatedAt = null;
        $audioAppendedAt = null;
        $audioCommittedAt = null;
        $sessionSnapshot = null;

        try {
            $client->run(
                onEvent: function (array $event) use (&$receivedTypes, &$providerError, &$responseDone, &$sessionReady, &$sessionUpdatedAt, &$sessionSnapshot): void {
                    $type = (string) ($event['type'] ?? 'unknown');
                    $receivedTypes[] = $type;

                    if ($type === 'error') {
                        $providerError = is_array($event['error'] ?? null) ? $event['error'] : $event;
                    }

                    if ($type === 'session.updated') {
                        $sessionReady = true;
                        $sessionUpdatedAt = now();
                        $sessionSnapshot = is_array($event['session'] ?? null) ? $event['session'] : null;
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
                            'instructions' => 'Handle audio input safely and briefly.',
                            'output_modalities' => ['text'],
                            'audio' => [
                                'input' => [
                                    'format' => [
                                        'type' => 'audio/pcm',
                                        'rate' => 24000,
                                    ],
                                    'turn_detection' => null,
                                ],
                            ],
                        ],
                    ]);
                },
                onTick: function () use (
                    $client,
                    $audioChunks,
                    &$sessionReady,
                    &$audioAppended,
                    &$audioCommitted,
                    &$responseRequested,
                    &$sessionUpdatedAt,
                    &$audioAppendedAt,
                    &$audioCommittedAt
                ): void {
                    if ($sessionReady && ! $audioAppended && $sessionUpdatedAt !== null && $sessionUpdatedAt->diffInMilliseconds(now()) >= 250) {
                        foreach ($audioChunks as $chunk) {
                            $client->send([
                                'type' => 'input_audio_buffer.append',
                                'audio' => $chunk,
                            ]);
                        }

                        $audioAppended = true;
                        $audioAppendedAt = now();

                        return;
                    }

                    if ($audioAppended && ! $audioCommitted && $audioAppendedAt !== null && $audioAppendedAt->diffInMilliseconds(now()) >= 300) {
                        $client->send([
                            'type' => 'input_audio_buffer.commit',
                        ]);

                        $audioCommitted = true;
                        $audioCommittedAt = now();

                        return;
                    }

                    if ($audioCommitted && ! $responseRequested && $audioCommittedAt !== null && $audioCommittedAt->diffInMilliseconds(now()) >= 300) {
                        $client->send([
                            'type' => 'response.create',
                            'response' => [
                                'instructions' => 'If there is no intelligible speech, reply briefly.',
                            ],
                        ]);

                        $responseRequested = true;
                    }
                },
                shouldStop: function () use (&$responseDone, &$providerError, $deadline): bool {
                    return $responseDone || is_array($providerError) || now()->greaterThanOrEqualTo($deadline);
                },
                timeoutSeconds: 25,
            );
        } catch (AiAssistantException $exception) {
            $this->error($exception->errorCode().': '.$exception->getMessage());

            return self::FAILURE;
        }

        if (is_array($providerError)) {
            $this->error('Realtime audio buffer smoke hit provider error.');
            $this->line('Observed events: '.implode(', ', array_slice($receivedTypes, 0, 20)));
            $this->line('Provider error: '.json_encode($providerError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (is_array($sessionSnapshot)) {
                $this->line('Session snapshot: '.json_encode($sessionSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::FAILURE;
        }

        if (! $responseDone) {
            $this->error('Realtime audio buffer smoke did not reach response.done.');
            $this->line('Observed events: '.implode(', ', array_slice($receivedTypes, 0, 20)));

            return self::FAILURE;
        }

        $this->info('Realtime audio buffer smoke ok.');
        $this->line('Observed events: '.implode(', ', array_slice($receivedTypes, 0, 20)));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function buildPcm16ToneChunks(): array
    {
        $sampleRate = 24000;
        $durationSeconds = 0.35;
        $frequency = 440.0;
        $amplitude = 0.35;
        $totalSamples = (int) ($sampleRate * $durationSeconds);

        $pcm = '';

        for ($i = 0; $i < $totalSamples; $i++) {
            $sample = (int) round(sin(2 * M_PI * $frequency * ($i / $sampleRate)) * 32767 * $amplitude);
            $pcm .= pack('v', $sample & 0xffff);
        }

        $chunks = [];
        $chunkBytes = 8192;

        for ($offset = 0; $offset < strlen($pcm); $offset += $chunkBytes) {
            $chunks[] = base64_encode(substr($pcm, $offset, $chunkBytes));
        }

        return $chunks;
    }
}
