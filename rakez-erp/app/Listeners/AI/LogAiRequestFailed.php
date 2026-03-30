<?php

namespace App\Listeners\AI;

use App\Events\AI\AiRequestFailed;
use App\Models\AiInteractionLog;
use App\Services\AI\AiAuditService;

class LogAiRequestFailed
{
    public function __construct(
        private readonly AiAuditService $auditService,
    ) {}

    public function handle(AiRequestFailed $event): void
    {
        AiInteractionLog::create([
            'user_id' => $event->userId,
            'session_id' => $event->sessionId,
            'correlation_id' => $event->correlationId,
            'section' => null,
            'request_type' => 'error',
            'model' => 'n/a',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'latency_ms' => 0,
            'tool_calls_count' => 0,
            'had_error' => true,
            'error_message' => $event->error,
        ]);

        $this->auditService->recordByUserId(
            $event->userId,
            'ai_request_failed',
            'ai_session',
            null,
            ['attempts' => $event->attempts],
            ['error' => $event->error],
            $event->correlationId,
        );
    }
}
