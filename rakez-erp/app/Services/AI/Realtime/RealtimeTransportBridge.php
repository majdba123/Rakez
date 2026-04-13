<?php

namespace App\Services\AI\Realtime;

use App\Models\AiRealtimeSession;
use App\Services\AI\Exceptions\AiAssistantException;
use Throwable;

class RealtimeTransportBridge
{
    public function __construct(
        private readonly RealtimeTransportClient $client,
        private readonly RealtimeSessionBroker $broker,
        private readonly RealtimeTransportEventStore $eventStore,
        private readonly RealtimeAuditService $auditService,
        private readonly RealtimeBridgeLeaseService $leaseService,
        private readonly RealtimeToolExecutor $toolExecutor,
    ) {}

    public function run(AiRealtimeSession $session, int $maxRuntimeSeconds = 300, string $ownerToken = ''): void
    {
        if (! config('ai_realtime.enabled', false)) {
            throw new AiAssistantException('Realtime backend is currently disabled.', 'ai_realtime_disabled', 503);
        }

        $ownerToken = $ownerToken !== '' ? $ownerToken : (string) $session->bridge_owner_token;
        $startedSession = $session->fresh();
        $startedSession = $this->leaseService->acquire($startedSession, $ownerToken, getmypid() ?: null);
        if ($startedSession->status === AiRealtimeSession::STATUS_SESSION_CREATED) {
            $startedSession = $this->broker->start($startedSession);
        }

        $startedAt = now();
        $this->markBridgeState($startedSession, 'connecting');
        $disconnectReason = 'transport_loop_exited';

        try {
            $this->client->run(
                onEvent: function (array $event) use ($startedSession, $ownerToken): void {
                    $this->handleProviderEvent($startedSession->fresh(), $event, $ownerToken);
                },
                onOpen: function () use ($startedSession): void {
                    $fresh = $startedSession->fresh();

                    $this->markBridgeState($fresh, 'running');
                    $this->auditService->record(
                        $fresh,
                        'transport_bridge_connected',
                        ['transport' => 'openai_realtime_ws'],
                        $fresh->status,
                        $fresh->status
                    );

                    $this->client->send($this->initialSessionUpdatePayload($fresh));
                },
                onTick: function () use ($startedSession, $ownerToken): void {
                    $fresh = $startedSession->fresh();
                    $this->leaseService->heartbeat($fresh, $ownerToken);

                    foreach ($this->eventStore->pendingClientEvents($fresh) as $event) {
                        $payload = $event->payload ?? [];
                        if (is_array($payload)) {
                            $this->client->send($payload);
                        }

                        $this->eventStore->markProcessed($event);
                    }
                },
                shouldStop: function () use ($startedSession, $startedAt, $maxRuntimeSeconds, $ownerToken): bool {
                    $fresh = $startedSession->fresh();
                    $bridge = $fresh->metadata['bridge'] ?? [];
                    $stopRequested = (bool) ($bridge['stop_requested'] ?? false);

                    if (! $this->leaseService->isOwnedBy($fresh, $ownerToken)) {
                        return true;
                    }

                    if ($stopRequested) {
                        return true;
                    }

                    if ($fresh->status === AiRealtimeSession::STATUS_ENDED) {
                        return true;
                    }

                    return $startedAt->diffInSeconds(now()) >= $maxRuntimeSeconds;
                },
                timeoutSeconds: 30,
            );
        } catch (Throwable $throwable) {
            $disconnectReason = $throwable instanceof AiAssistantException
                ? $throwable->errorCode()
                : 'transport_client_exception';

            $this->auditService->record(
                $startedSession->fresh(),
                'transport_bridge_failed',
                [
                    'reason' => $disconnectReason,
                    'exception' => class_basename($throwable),
                ],
                $startedSession->status,
                $startedSession->status,
                errorCode: 'ai_realtime_transport_failed'
            );
        } finally {
            $this->leaseService->release($startedSession->fresh(), $ownerToken);
        }

        $fresh = $startedSession->fresh();
        $bridge = $fresh->metadata['bridge'] ?? [];

        if (($bridge['stop_requested'] ?? false) === true || $fresh->status === AiRealtimeSession::STATUS_ENDED) {
            $this->markBridgeState($fresh, 'stopped');

            return;
        }

        $this->markBridgeState($fresh, 'disconnected');
        try {
            $disconnected = $this->broker->markTransportDisconnected($fresh, [
                'reason' => $disconnectReason,
            ]);

            if ($disconnected->status === AiRealtimeSession::STATUS_RECONNECTING) {
                $this->markBridgeState($disconnected, 'reconnecting');
            }

            if ($disconnected->status === AiRealtimeSession::STATUS_ENDED) {
                $this->markBridgeState($disconnected, 'rolled_back');
            }
        } catch (AiAssistantException) {
            $this->eventStore->requestStop($fresh, [
                'reason' => $disconnectReason,
            ]);
            $this->auditService->record(
                $fresh->fresh(),
                'transport_bridge_disconnected',
                ['reason' => $disconnectReason],
                $fresh->status,
                $fresh->status
            );
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleProviderEvent(AiRealtimeSession $session, array $event, string $ownerToken): void
    {
        if (! isset($event['type']) || ! is_string($event['type']) || $event['type'] === '') {
            $this->auditService->record(
                $session,
                'transport_provider_event_malformed',
                ['payload_keys' => array_keys($event)],
                $session->status,
                $session->status,
                errorCode: 'ai_realtime_provider_event_malformed'
            );

            return;
        }

        $eventType = (string) $event['type'];
        $eventId = isset($event['event_id']) && is_string($event['event_id']) ? $event['event_id'] : null;

        $stored = $this->eventStore->recordProviderEvent($session, $eventType, $event, $eventId);
        if ($eventId !== null && ! $stored->wasRecentlyCreated) {
            $this->auditService->record(
                $session,
                'provider_event_duplicate_ignored',
                ['event_type' => $eventType, 'event_id' => $eventId],
                $session->status,
                $session->status
            );

            return;
        }

        if (in_array($eventType, ['session.created', 'session.updated'], true)) {
            $providerSessionId = $event['session']['id'] ?? null;
            $metadata = array_merge($session->metadata ?? [], [
                'provider_connected' => true,
                'last_provider_event_type' => $eventType,
            ]);

            $session->update([
                'provider_session_id' => is_string($providerSessionId) ? $providerSessionId : $session->provider_session_id,
                'transport_status' => 'connected',
                'metadata' => $metadata,
                'last_activity_at' => now(),
            ]);

            if ($session->status === AiRealtimeSession::STATUS_RECONNECTING) {
                try {
                    $restored = $this->broker->restoreAfterReconnect($session->fresh());
                    $this->resumeDeferredToolResultIfNeeded($restored);
                } catch (AiAssistantException) {
                }
            }

            return;
        }

        if (in_array($eventType, ['response.output_item.added', 'response.output_item.done'], true)) {
            $item = $event['item'] ?? null;

            if (is_array($item) && ($item['type'] ?? null) === 'function_call') {
                $toolName = is_string($item['name'] ?? null) ? $item['name'] : 'unknown_tool';
                $callId = is_string($item['call_id'] ?? null) ? $item['call_id'] : null;

                try {
                    if ($eventType === 'response.output_item.added') {
                        $this->broker->startTool($session, $toolName, $callId);
                    } else {
                        $execution = $this->toolExecutor->execute($session->fresh(), $item);
                        $current = $session->fresh();
                        if (($current->metadata['active_tool'] ?? null) === $toolName) {
                            $this->client->send([
                                'type' => 'conversation.item.create',
                                'item' => [
                                    'type' => 'function_call_output',
                                    'call_id' => $execution['call_id'],
                                    'output' => $execution['output'],
                                ],
                            ]);

                            $finished = $this->broker->finishTool($current, $toolName, $callId);
                            if ($this->shouldContinueAfterTool($finished)) {
                                $this->client->send(['type' => 'response.create']);
                            }

                            if ($finished->status === AiRealtimeSession::STATUS_RECONNECTING) {
                                $this->auditService->record(
                                    $finished,
                                    'tool_result_waiting_for_reconnect',
                                    ['tool_name' => $toolName, 'call_id' => $callId],
                                    $finished->status,
                                    $finished->status
                                );
                            }
                        }
                    }
                } catch (AiAssistantException) {
                }

                return;
            }
        }

        if ($eventType === 'response.created' && $session->status !== AiRealtimeSession::STATUS_ASSISTANT_THINKING) {
            try {
                $this->broker->markThinking($session);
            } catch (AiAssistantException) {
            }

            return;
        }

        if (str_starts_with($eventType, 'response.output_') && $session->status !== AiRealtimeSession::STATUS_ASSISTANT_SPEAKING) {
            try {
                $this->broker->markSpeaking($session);
            } catch (AiAssistantException) {
            }

            return;
        }

        if ($eventType === 'response.done') {
            $usage = $event['response']['usage'] ?? null;
            if (is_array($usage)) {
                $this->broker->recordUsage($session->fresh(), $usage);
            }

            try {
                $this->broker->markListening($session->fresh());
            } catch (AiAssistantException) {
            }

            return;
        }

        if ($eventType === 'input_audio_buffer.speech_started') {
            try {
                $this->broker->interrupt($session, 'provider_detected_barge_in');
            } catch (AiAssistantException) {
            }

            return;
        }

        if ($eventType === 'error') {
            $this->auditService->record(
                $session,
                'transport_provider_error',
                ['provider_error' => $event['error']['message'] ?? 'unknown'],
                $session->status,
                $session->status,
                errorCode: 'ai_realtime_provider_error'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function initialSessionUpdatePayload(AiRealtimeSession $session): array
    {
        return [
            'type' => 'session.update',
            'session' => [
                'type' => 'realtime',
                'model' => (string) config('ai_realtime.openai.model'),
                'instructions' => 'You are the governed AlgoAG realtime assistant. Never bypass policy or perform business-table writes.',
                'output_modalities' => ['audio'],
                'audio' => [
                    'input' => [
                        'turn_detection' => [
                            'type' => (string) config('ai_realtime.openai.turn_detection', 'semantic_vad'),
                            'interrupt_response' => true,
                            'create_response' => true,
                        ],
                    ],
                    'output' => [
                        'voice' => (string) config('ai_realtime.openai.voice', 'marin'),
                    ],
                ],
            ],
        ];
    }

    private function markBridgeState(AiRealtimeSession $session, string $status): void
    {
        $metadata = $session->metadata ?? [];
        $metadata['bridge'] = array_merge($metadata['bridge'] ?? [], [
            'status' => $status,
            'stop_requested' => $metadata['bridge']['stop_requested'] ?? false,
            'updated_at' => now()->toIso8601String(),
        ]);

        $session->update([
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ]);
    }

    private function shouldContinueAfterTool(AiRealtimeSession $session): bool
    {
        $bridge = $session->metadata['bridge'] ?? [];

        return ! in_array($session->status, [AiRealtimeSession::STATUS_INTERRUPTED, AiRealtimeSession::STATUS_RECONNECTING, AiRealtimeSession::STATUS_ENDED], true)
            && ! (bool) ($session->metadata['tool_cancellation_requested'] ?? false)
            && ! (bool) ($bridge['stop_requested'] ?? false);
    }

    private function resumeDeferredToolResultIfNeeded(AiRealtimeSession $session): void
    {
        $deferred = $session->metadata['deferred_tool_result'] ?? null;
        if (! is_array($deferred)) {
            return;
        }

        $bridge = $session->metadata['bridge'] ?? [];
        if ((bool) ($bridge['stop_requested'] ?? false)) {
            return;
        }

        $this->client->send(['type' => 'response.create']);
        $this->broker->clearDeferredToolResult($session);
        $this->auditService->record(
            $session->fresh(),
            'deferred_tool_result_resumed',
            [
                'tool_name' => $deferred['tool_name'] ?? 'unknown_tool',
                'call_id' => $deferred['call_id'] ?? null,
            ],
            $session->status,
            $session->status
        );
    }
}
