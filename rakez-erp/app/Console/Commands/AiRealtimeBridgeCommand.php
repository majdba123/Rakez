<?php

namespace App\Console\Commands;

use App\Models\AiRealtimeSession;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeTransportBridge;
use Illuminate\Console\Command;

class AiRealtimeBridgeCommand extends Command
{
    protected $signature = 'ai:realtime-bridge
        {session : Public realtime session ID}
        {--max-runtime=300 : Maximum runtime in seconds for the bridge loop}
        {--owner-token= : Expected bridge ownership token}';

    protected $description = 'Run the backend OpenAI Realtime bridge worker for a single session.';

    public function handle(RealtimeTransportBridge $bridge): int
    {
        $session = AiRealtimeSession::query()
            ->where('public_id', (string) $this->argument('session'))
            ->first();

        if (! $session) {
            $this->error('Realtime session not found.');

            return self::FAILURE;
        }

        try {
            $bridge->run(
                $session,
                (int) $this->option('max-runtime'),
                (string) ($this->option('owner-token') ?: '')
            );

            return self::SUCCESS;
        } catch (AiAssistantException $exception) {
            $this->error($exception->errorCode().': '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
