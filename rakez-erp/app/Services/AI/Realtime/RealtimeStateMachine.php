<?php

namespace App\Services\AI\Realtime;

use App\Models\AiRealtimeSession;

class RealtimeStateMachine
{
    /**
     * @return array<string, array<int, string>>
     */
    public function transitions(): array
    {
        return [
            AiRealtimeSession::STATUS_SESSION_CREATED => [
                AiRealtimeSession::STATUS_SESSION_ACTIVE,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_SESSION_ACTIVE => [
                AiRealtimeSession::STATUS_LISTENING,
                AiRealtimeSession::STATUS_RECONNECTING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_LISTENING => [
                AiRealtimeSession::STATUS_PARTIAL_TRANSCRIPT,
                AiRealtimeSession::STATUS_ASSISTANT_THINKING,
                AiRealtimeSession::STATUS_INTERRUPTED,
                AiRealtimeSession::STATUS_RECONNECTING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_PARTIAL_TRANSCRIPT => [
                AiRealtimeSession::STATUS_LISTENING,
                AiRealtimeSession::STATUS_ASSISTANT_THINKING,
                AiRealtimeSession::STATUS_INTERRUPTED,
                AiRealtimeSession::STATUS_RECONNECTING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_ASSISTANT_THINKING => [
                AiRealtimeSession::STATUS_LISTENING,
                AiRealtimeSession::STATUS_TOOL_RUNNING,
                AiRealtimeSession::STATUS_ASSISTANT_SPEAKING,
                AiRealtimeSession::STATUS_INTERRUPTED,
                AiRealtimeSession::STATUS_RECONNECTING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_TOOL_RUNNING => [
                AiRealtimeSession::STATUS_ASSISTANT_THINKING,
                AiRealtimeSession::STATUS_LISTENING,
                AiRealtimeSession::STATUS_ASSISTANT_SPEAKING,
                AiRealtimeSession::STATUS_INTERRUPTED,
                AiRealtimeSession::STATUS_RECONNECTING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_ASSISTANT_SPEAKING => [
                AiRealtimeSession::STATUS_LISTENING,
                AiRealtimeSession::STATUS_INTERRUPTED,
                AiRealtimeSession::STATUS_RECONNECTING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_INTERRUPTED => [
                AiRealtimeSession::STATUS_RECONNECTING,
                AiRealtimeSession::STATUS_LISTENING,
                AiRealtimeSession::STATUS_ASSISTANT_THINKING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_RECONNECTING => [
                AiRealtimeSession::STATUS_SESSION_ACTIVE,
                AiRealtimeSession::STATUS_LISTENING,
                AiRealtimeSession::STATUS_ENDED,
            ],
            AiRealtimeSession::STATUS_ENDED => [],
        ];
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->transitions()[$from] ?? [], true);
    }
}
