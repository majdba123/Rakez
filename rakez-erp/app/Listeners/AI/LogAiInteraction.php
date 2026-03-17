<?php

namespace App\Listeners\AI;

use App\Events\AI\AiRequestCompleted;
use App\Models\AiInteractionLog;

class LogAiInteraction
{
    public function handle(AiRequestCompleted $event): void
    {
        AiInteractionLog::create([
            'user_id' => $event->userId,
            'session_id' => $event->sessionId,
            'section' => $event->section,
            'request_type' => $event->requestType,
            'model' => $event->model,
            'prompt_tokens' => $event->promptTokens,
            'completion_tokens' => $event->completionTokens,
            'total_tokens' => $event->totalTokens,
            'latency_ms' => $event->latencyMs,
            'tool_calls_count' => $event->toolCallsCount,
            'had_error' => false,
            'error_message' => null,
        ]);
    }
}
