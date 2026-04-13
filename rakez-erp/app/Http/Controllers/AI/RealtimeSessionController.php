<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiRealtimeSession;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Realtime\RealtimeClientEventValidator;
use App\Services\AI\Realtime\RealtimeSessionBroker;
use App\Services\AI\Realtime\RealtimeTransportEventStore;
use App\Services\AI\Realtime\RealtimeTransportManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeSessionController extends Controller
{
    public function __construct(
        private readonly RealtimeSessionBroker $broker,
        private readonly RealtimeClientEventValidator $clientEventValidator,
        private readonly RealtimeTransportEventStore $transportEventStore,
        private readonly RealtimeTransportManager $transportManager,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => 'nullable|string|max:100',
            'requested_modalities' => 'nullable|array',
        ]);

        try {
            $session = $this->broker->create($request->user(), $validated);

            return response()->json([
                'success' => true,
                'data' => $this->serialize($session),
            ], 201);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function show(AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($session->load(['events' => fn ($q) => $q->orderBy('sequence')])),
        ]);
    }

    public function start(AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->start($session)),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function heartbeat(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'transport_status' => 'nullable|string|max:50',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->heartbeat($session, $validated)),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function listening(AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->markListening($session)),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function partialTranscript(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'text' => 'required|string|max:4000',
            'is_final' => 'nullable|boolean',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->updatePartialTranscript($session, $validated)),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function thinking(AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->markThinking($session)),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function speaking(AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->markSpeaking($session)),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function toolStart(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'tool_name' => 'required|string|max:120',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->startTool($session, (string) $validated['tool_name'])),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function toolFinish(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'tool_name' => 'required|string|max:120',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->finishTool($session, (string) $validated['tool_name'])),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function interrupt(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:120',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->interrupt($session, (string) ($validated['reason'] ?? 'user_barge_in'))),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function reconnect(AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->reconnect($session)),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function clientEvent(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'type' => 'required|string|in:input_audio_buffer.append,input_audio_buffer.commit,response.cancel,response.create,conversation.item.create',
            'event' => 'nullable|array',
        ]);

        try {
            $event = $this->clientEventValidator->validate(
                (string) $validated['type'],
                is_array($validated['event'] ?? null) ? $validated['event'] : []
            );

            $stored = $this->transportEventStore->enqueueClientEvent($session, $validated['type'], $event);

            return response()->json([
                'success' => true,
                'data' => [
                    'queued' => true,
                    'sequence' => $stored->sequence,
                    'type' => $stored->transport_event_type,
                ],
            ], 202);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function bridgeStart(AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->transportManager->start($session),
            ], 202);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function bridgeStop(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:191',
        ]);

        $this->transportManager->stop($session, (string) ($validated['reason'] ?? 'stop_requested'));

        return response()->json([
            'success' => true,
            'data' => [
                'stop_requested' => true,
            ],
        ], 202);
    }

    public function rollback(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:191',
            'target' => 'nullable|string|max:50',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->rollback(
                    $session,
                    (string) ($validated['target'] ?? 'voice_fallback'),
                    $validated['reason'] ?? null
                )),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    public function terminate(Request $request, AiRealtimeSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:191',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->serialize($this->broker->terminate($session, (string) ($validated['reason'] ?? 'session_terminated'))),
            ]);
        } catch (AiAssistantException $exception) {
            return $this->error($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AiRealtimeSession $session): array
    {
        return [
            'id' => $session->public_id,
            'status' => $session->status,
            'transport' => $session->transport,
            'transport_mode' => $session->transport_mode,
            'transport_status' => $session->transport_status,
            'section' => $session->section,
            'provider_model' => $session->provider_model,
            'rollback_target' => $session->rollback_target,
            'correlation_id' => $session->correlation_id,
            'duration_limit_seconds' => $session->duration_limit_seconds,
            'reconnect_count' => $session->reconnect_count,
            'max_reconnects' => $session->max_reconnects,
            'turn_number' => $session->turn_number,
            'estimated_input_tokens' => $session->estimated_input_tokens,
            'estimated_output_tokens' => $session->estimated_output_tokens,
            'estimated_total_tokens' => $session->estimated_total_tokens,
            'started_at' => optional($session->started_at)->toIso8601String(),
            'last_activity_at' => optional($session->last_activity_at)->toIso8601String(),
            'expires_at' => optional($session->expires_at)->toIso8601String(),
            'ended_at' => optional($session->ended_at)->toIso8601String(),
            'metadata' => $session->metadata ?? [],
            'events' => $session->relationLoaded('events')
                ? $session->events->map(fn ($event) => [
                    'sequence' => $event->sequence,
                    'direction' => $event->direction,
                    'event_type' => $event->event_type,
                    'transport_event_type' => $event->transport_event_type,
                    'transport_event_id' => $event->transport_event_id,
                    'state_before' => $event->state_before,
                    'state_after' => $event->state_after,
                    'processed_at' => optional($event->processed_at)->toIso8601String(),
                    'payload' => $event->payload,
                    'created_at' => optional($event->created_at)->toIso8601String(),
                ])->values()->all()
                : null,
        ];
    }

    private function error(AiAssistantException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => $exception->errorCode(),
            'message' => $exception->getMessage(),
        ], $exception->statusCode());
    }

    private function authorizeSessionAccess(AiRealtimeSession $session): void
    {
        if ((int) request()->user()?->id !== (int) $session->user_id) {
            abort(403, 'You do not have access to this realtime session.');
        }
    }
}
