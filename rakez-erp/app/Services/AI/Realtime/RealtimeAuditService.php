<?php

namespace App\Services\AI\Realtime;

use App\Models\AiRealtimeSession;
use App\Models\AiRealtimeSessionEvent;
use App\Services\AI\AiAuditService;

class RealtimeAuditService
{
    public function __construct(
        private readonly AiAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        AiRealtimeSession $session,
        string $eventType,
        array $payload = [],
        ?string $stateBefore = null,
        ?string $stateAfter = null,
        string $direction = 'internal',
        ?string $transportEventType = null,
        ?string $transportEventId = null,
        ?string $errorCode = null,
        ?\DateTimeInterface $processedAt = null,
    ): AiRealtimeSessionEvent {
        $sequence = ((int) $session->events()->max('sequence')) + 1;

        $event = AiRealtimeSessionEvent::create([
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'sequence' => $sequence,
            'direction' => $direction,
            'event_type' => $eventType,
            'transport_event_type' => $transportEventType,
            'transport_event_id' => $transportEventId,
            'error_code' => $errorCode,
            'state_before' => $stateBefore,
            'state_after' => $stateAfter,
            'correlation_id' => $session->correlation_id,
            'payload' => $payload,
            'processed_at' => $processedAt,
        ]);

        $this->auditService->recordByUserId(
            $session->user_id,
            'realtime_'.$eventType,
            'ai_realtime_session',
            $session->id,
            [
                'public_id' => $session->public_id,
                'state_before' => $stateBefore,
            ],
            [
                'state_after' => $stateAfter,
                'payload_keys' => array_keys($payload),
            ],
            $session->correlation_id
        );

        return $event;
    }
}
