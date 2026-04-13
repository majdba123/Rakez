<?php

namespace App\Console\Commands;

use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeGateway;
use Illuminate\Console\Command;

class AiRealtimeSmokeTest extends Command
{
    protected $signature = 'ai:realtime-smoke-test';

    protected $description = 'Probe OpenAI Realtime bootstrap connectivity through the backend gateway.';

    public function handle(RealtimeGateway $gateway): int
    {
        try {
            $result = $gateway->createClientSecretProbe();

            $this->info('Realtime probe ok.');
            $this->line('Probe ID: '.($result['probe_id'] ?? 'n/a'));
            $this->line('Session ID: '.($result['session_id'] ?? 'n/a'));

            return self::SUCCESS;
        } catch (AiAssistantException $exception) {
            $this->error($exception->errorCode().': '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
