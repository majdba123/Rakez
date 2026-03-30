<?php

namespace App\Listeners\AI;

use App\Events\AI\AiToolExecuted;
use App\Services\AI\AiAuditService;

class LogAiToolExecutedAudit
{
    public function __construct(
        private readonly AiAuditService $auditService,
    ) {}

    public function handle(AiToolExecuted $event): void
    {
        $this->auditService->recordByUserId(
            $event->userId,
            'tool_call',
            'ai_tool',
            null,
            [
                'tool' => $event->toolName,
                'duration_ms' => $event->durationMs,
                'denied' => $event->denied,
            ],
            [],
            $event->correlationId,
        );
    }
}
