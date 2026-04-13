<?php

namespace App\Services\AI\Realtime;

use App\Models\AiRealtimeSession;
use App\Models\AiRealtimeSessionEvent;

class RealtimeTransportEventStore
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function enqueueClientEvent(
        AiRealtimeSession $session,
        string $transportEventType,
        array $payload,
    ): AiRealtimeSessionEvent {
        return $this->createTransportEvent(
            $session,
            direction: 'client_to_provider',
            eventType: 'client_event_queued',
            transportEventType: $transportEventType,
            payload: $payload
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordProviderEvent(
        AiRealtimeSession $session,
        string $transportEventType,
        array $payload,
        ?string $transportEventId = null,
    ): AiRealtimeSessionEvent {
        if ($transportEventId !== null) {
            $existing = AiRealtimeSessionEvent::query()
                ->where('session_id', $session->id)
                ->where('direction', 'provider_to_client')
                ->where('transport_event_id', $transportEventId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return $this->createTransportEvent(
            $session,
            direction: 'provider_to_client',
            eventType: 'provider_event_received',
            transportEventType: $transportEventType,
            payload: $payload,
            transportEventId: $transportEventId,
            processedAt: now()
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, AiRealtimeSessionEvent>
     */
    public function pendingClientEvents(AiRealtimeSession $session, int $limit = 25)
    {
        return AiRealtimeSessionEvent::query()
            ->where('session_id', $session->id)
            ->where('direction', 'client_to_provider')
            ->whereNull('processed_at')
            ->orderBy('sequence')
            ->limit($limit)
            ->get();
    }

    public function markProcessed(AiRealtimeSessionEvent $event): void
    {
        $event->forceFill([
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function requestStop(AiRealtimeSession $session, array $payload = []): AiRealtimeSessionEvent
    {
        return $this->createTransportEvent(
            $session,
            direction: 'internal',
            eventType: 'transport_stop_requested',
            transportEventType: 'transport.stop',
            payload: $payload,
            processedAt: null
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createTransportEvent(
        AiRealtimeSession $session,
        string $direction,
        string $eventType,
        string $transportEventType,
        array $payload,
        ?string $transportEventId = null,
        ?\DateTimeInterface $processedAt = null,
    ): AiRealtimeSessionEvent {
        $sequence = ((int) $session->events()->max('sequence')) + 1;

        return AiRealtimeSessionEvent::create([
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'sequence' => $sequence,
            'direction' => $direction,
            'event_type' => $eventType,
            'transport_event_type' => $transportEventType,
            'transport_event_id' => $transportEventId,
            'state_before' => $session->status,
            'state_after' => $session->status,
            'correlation_id' => $session->correlation_id,
            'payload' => $payload,
            'processed_at' => $processedAt,
        ]);
    }
}
