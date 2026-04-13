<?php

namespace App\Services\AI\Realtime;

use App\Models\AIConversation;
use App\Models\AiRealtimeSession;
use App\Models\User;
use App\Services\AI\Exceptions\AiAssistantException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealtimeSessionBroker
{
    public function __construct(
        private readonly RealtimeStateMachine $stateMachine,
        private readonly RealtimeAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, array $input = []): AiRealtimeSession
    {
        $this->ensureRealtimeEnabled();
        $this->ensureBudgetAvailable($user);
        $this->ensureActiveSessionCapacity($user);

        $now = Carbon::now();
        $durationLimit = (int) config('ai_realtime.sessions.max_duration_seconds', 900);
        $maxReconnects = (int) config('ai_realtime.sessions.max_reconnects', 3);

        $session = AiRealtimeSession::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => AiRealtimeSession::STATUS_SESSION_CREATED,
            'transport' => 'websocket',
            'transport_mode' => (string) config('ai_realtime.sessions.transport_mode', 'control_plane_only'),
            'transport_status' => 'not_connected',
            'section' => $input['section'] ?? null,
            'provider_model' => config('ai_realtime.openai.model'),
            'rollback_target' => (string) config('ai_realtime.sessions.rollback_target', 'voice_fallback'),
            'correlation_id' => (string) Str::uuid(),
            'duration_limit_seconds' => $durationLimit,
            'max_reconnects' => $maxReconnects,
            'reconnect_count' => 0,
            'turn_number' => 0,
            'last_activity_at' => $now,
            'expires_at' => $now->copy()->addSeconds($durationLimit),
            'metadata' => [
                'requested_modalities' => $input['requested_modalities'] ?? ['audio', 'text'],
                'live_tools_enabled' => false,
                'provider_connected' => false,
                'fallback_allowed' => true,
            ],
        ]);

        $this->auditService->record($session, 'session_created', [
            'section' => $session->section,
            'transport_mode' => $session->transport_mode,
        ], null, $session->status);

        return $session;
    }

    public function start(AiRealtimeSession $session): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_SESSION_ACTIVE, 'session_started', [
            'transport_status' => 'awaiting_provider_transport',
        ], fn (AiRealtimeSession $item) => [
            'started_at' => $item->started_at ?: now(),
            'transport_status' => 'awaiting_provider_transport',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function heartbeat(AiRealtimeSession $session, array $payload = []): AiRealtimeSession
    {
        $this->ensureUsable($session);

        $session->update([
            'last_activity_at' => now(),
            'metadata' => array_merge($session->metadata ?? [], [
                'last_heartbeat' => [
                    'received_at' => now()->toIso8601String(),
                    'transport_status' => $payload['transport_status'] ?? $session->transport_status,
                ],
            ]),
        ]);

        $this->auditService->record($session->fresh(), 'session_heartbeat', [
            'transport_status' => $payload['transport_status'] ?? $session->transport_status,
        ], $session->status, $session->status);

        return $session->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updatePartialTranscript(AiRealtimeSession $session, array $payload): AiRealtimeSession
    {
        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') {
            throw new AiAssistantException('Partial transcript text is required.', 'ai_realtime_validation_failed', 422);
        }

        return $this->transition($session, AiRealtimeSession::STATUS_PARTIAL_TRANSCRIPT, 'partial_transcript_generated', [
            'text_preview' => mb_substr($text, 0, 200),
            'is_final' => (bool) ($payload['is_final'] ?? false),
        ], function (AiRealtimeSession $item) use ($text, $payload): array {
            return [
                'turn_number' => $item->turn_number + 1,
                'metadata' => array_merge($item->metadata ?? [], [
                    'partial_transcript' => [
                        'text' => $text,
                        'is_final' => (bool) ($payload['is_final'] ?? false),
                    ],
                ]),
            ];
        });
    }

    public function markListening(AiRealtimeSession $session): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_LISTENING, 'listening_started', []);
    }

    public function markThinking(AiRealtimeSession $session): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_ASSISTANT_THINKING, 'assistant_thinking_started', []);
    }

    public function markSpeaking(AiRealtimeSession $session): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_ASSISTANT_SPEAKING, 'assistant_output_started', []);
    }

    public function startTool(AiRealtimeSession $session, string $toolName, ?string $callId = null): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_TOOL_RUNNING, 'tool_call_started', [
            'tool_name' => $toolName,
            'call_id' => $callId,
        ], function (AiRealtimeSession $item) use ($toolName, $callId): array {
            if ($item->status === AiRealtimeSession::STATUS_TOOL_RUNNING || isset($item->metadata['active_tool'])) {
                throw new AiAssistantException('A realtime tool is already running for this session.', 'ai_realtime_tool_conflict', 409);
            }

            return [
                'metadata' => array_merge($item->metadata ?? [], [
                    'active_tool' => $toolName,
                    'active_tool_call_id' => $callId,
                ]),
            ];
        });
    }

    public function finishTool(AiRealtimeSession $session, string $toolName, ?string $callId = null): AiRealtimeSession
    {
        return $this->withLockedSession($session, function (AiRealtimeSession $locked) use ($toolName, $callId): AiRealtimeSession {
            $activeTool = $locked->metadata['active_tool'] ?? null;
            $activeToolCallId = $locked->metadata['active_tool_call_id'] ?? null;
            if ($activeTool !== $toolName || ($callId !== null && $activeToolCallId !== $callId)) {
                throw new AiAssistantException('The requested realtime tool is not the active tool for this session.', 'ai_realtime_tool_conflict', 409);
            }

            if (in_array($locked->status, [AiRealtimeSession::STATUS_INTERRUPTED, AiRealtimeSession::STATUS_RECONNECTING], true)) {
                $metadata = $locked->metadata ?? [];
                unset($metadata['active_tool'], $metadata['active_tool_call_id']);
                $metadata['deferred_tool_result'] = [
                    'tool_name' => $toolName,
                    'call_id' => $callId,
                    'finished_at' => now()->toIso8601String(),
                ];

                $locked->update([
                    'metadata' => $metadata,
                    'last_activity_at' => now(),
                ]);

                $fresh = $locked->fresh();
                $this->auditService->record($fresh, 'tool_result_deferred', [
                    'tool_name' => $toolName,
                    'call_id' => $callId,
                ], $fresh->status, $fresh->status);

                return $fresh;
            }

            return $this->transitionLocked($locked, AiRealtimeSession::STATUS_ASSISTANT_THINKING, 'tool_call_finished', [
                'tool_name' => $toolName,
                'call_id' => $callId,
            ], function (AiRealtimeSession $item): array {
                $metadata = $item->metadata ?? [];
                unset($metadata['active_tool'], $metadata['active_tool_call_id']);

                return ['metadata' => $metadata];
            });
        });
    }

    public function interrupt(AiRealtimeSession $session, string $reason = 'user_barge_in'): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_INTERRUPTED, 'interruption_occurred', [
            'reason' => $reason,
        ], function (AiRealtimeSession $item): array {
            $metadata = $item->metadata ?? [];
            if (isset($metadata['active_tool'])) {
                $metadata['tool_cancellation_requested'] = true;
            }

            return ['metadata' => $metadata];
        });
    }

    public function reconnect(AiRealtimeSession $session): AiRealtimeSession
    {
        return $this->withLockedSession($session, function (AiRealtimeSession $locked): AiRealtimeSession {
            $this->ensureUsable($locked);

            if ($locked->reconnect_count >= $locked->max_reconnects) {
                throw new AiAssistantException('Reconnect attempt rejected for this realtime session.', 'ai_realtime_reconnect_rejected', 429);
            }

            return $this->transitionLocked($locked, AiRealtimeSession::STATUS_RECONNECTING, 'reconnect_requested', [
                'attempt' => $locked->reconnect_count + 1,
            ], fn (AiRealtimeSession $item) => [
                'reconnect_count' => $item->reconnect_count + 1,
                'transport_status' => 'reconnecting',
            ]);
        });
    }

    public function restoreAfterReconnect(AiRealtimeSession $session): AiRealtimeSession
    {
        $target = $session->started_at !== null
            ? AiRealtimeSession::STATUS_LISTENING
            : AiRealtimeSession::STATUS_SESSION_ACTIVE;

        return $this->transition($session, $target, 'reconnect_restored', [
            'restored_to' => $target,
        ], function (AiRealtimeSession $item): array {
            $metadata = $item->metadata ?? [];
            unset($metadata['tool_cancellation_requested']);

            return [
                'transport_status' => 'connected',
                'metadata' => $metadata,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markTransportDisconnected(AiRealtimeSession $session, array $payload = []): AiRealtimeSession
    {
        return $this->withLockedSession($session, function (AiRealtimeSession $locked) use ($payload): AiRealtimeSession {
            $this->ensureUsable($locked);

            if ($locked->reconnect_count >= $locked->max_reconnects) {
                return $this->transitionLocked(
                    $locked,
                    AiRealtimeSession::STATUS_ENDED,
                    'rollback_to_fallback_used',
                    [
                        'target' => (string) ($payload['target'] ?? $locked->rollback_target ?? 'voice_fallback'),
                        'reason' => (string) ($payload['reason'] ?? 'provider_transport_unavailable'),
                    ],
                    fn () => [
                        'ended_at' => now(),
                        'transport_status' => 'rolled_back',
                        'rollback_target' => (string) ($payload['target'] ?? $locked->rollback_target ?? 'voice_fallback'),
                    ]
                );
            }

            return $this->transitionLocked($locked, AiRealtimeSession::STATUS_RECONNECTING, 'transport_disconnected', [
                'reason' => $payload['reason'] ?? 'provider_transport_unavailable',
                'attempt' => $locked->reconnect_count + 1,
            ], fn (AiRealtimeSession $item) => [
                'reconnect_count' => $item->reconnect_count + 1,
                'transport_status' => 'reconnecting',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    public function recordUsage(AiRealtimeSession $session, array $usage): AiRealtimeSession
    {
        return $this->withLockedSession($session, function (AiRealtimeSession $locked) use ($usage): AiRealtimeSession {
            $inputTokens = max(0, (int) ($usage['input_tokens'] ?? 0));
            $outputTokens = max(0, (int) ($usage['output_tokens'] ?? 0));
            $totalTokens = max(0, (int) ($usage['total_tokens'] ?? ($inputTokens + $outputTokens)));

            $nextInput = ((int) $locked->estimated_input_tokens) + $inputTokens;
            $nextOutput = ((int) $locked->estimated_output_tokens) + $outputTokens;
            $nextTotal = ((int) $locked->estimated_total_tokens) + $totalTokens;

            $metadata = array_merge($locked->metadata ?? [], [
                'last_usage' => [
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $totalTokens,
                    'captured_at' => now()->toIso8601String(),
                ],
                'usage_ledger' => [
                    'responses_counted' => (int) (($locked->metadata['usage_ledger']['responses_counted'] ?? 0) + 1),
                    'input_tokens' => $nextInput,
                    'output_tokens' => $nextOutput,
                    'total_tokens' => $nextTotal,
                ],
            ]);

            $locked->update([
                'estimated_input_tokens' => $nextInput,
                'estimated_output_tokens' => $nextOutput,
                'estimated_total_tokens' => $nextTotal,
                'metadata' => $metadata,
                'last_activity_at' => now(),
            ]);

            $fresh = $locked->fresh();
            $this->auditService->record($fresh, 'usage_telemetry_updated', [
                'delta_input_tokens' => $inputTokens,
                'delta_output_tokens' => $outputTokens,
                'delta_total_tokens' => $totalTokens,
                'session_total_tokens' => $nextTotal,
            ], $fresh->status, $fresh->status);

            return $this->enforceLiveBudget($fresh);
        });
    }

    public function clearDeferredToolResult(AiRealtimeSession $session): AiRealtimeSession
    {
        return $this->withLockedSession($session, function (AiRealtimeSession $locked): AiRealtimeSession {
            $metadata = $locked->metadata ?? [];
            unset($metadata['deferred_tool_result'], $metadata['tool_cancellation_requested']);

            $locked->update([
                'metadata' => $metadata,
                'last_activity_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function rollback(AiRealtimeSession $session, string $target = 'voice_fallback', ?string $reason = null): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_ENDED, 'rollback_to_fallback_used', [
            'target' => $target,
            'reason' => $reason ?: 'provider_transport_unavailable',
        ], fn () => [
            'ended_at' => now(),
            'transport_status' => 'rolled_back',
            'rollback_target' => $target,
        ]);
    }

    public function terminate(AiRealtimeSession $session, string $reason = 'session_terminated'): AiRealtimeSession
    {
        return $this->transition($session, AiRealtimeSession::STATUS_ENDED, 'session_terminated', [
            'reason' => $reason,
        ], fn () => [
            'ended_at' => now(),
            'transport_status' => 'closed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(AiRealtimeSession): array<string, mixed>|null  $mutator
     */
    private function transition(
        AiRealtimeSession $session,
        string $to,
        string $eventType,
        array $payload,
        ?callable $mutator = null,
    ): AiRealtimeSession {
        return $this->withLockedSession($session, fn (AiRealtimeSession $locked) => $this->transitionLocked(
            $locked,
            $to,
            $eventType,
            $payload,
            $mutator
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(AiRealtimeSession): array<string, mixed>|null  $mutator
     */
    private function transitionLocked(
        AiRealtimeSession $session,
        string $to,
        string $eventType,
        array $payload,
        ?callable $mutator = null,
    ): AiRealtimeSession {
        $this->ensureUsable($session, allowEnded: $to === AiRealtimeSession::STATUS_ENDED);

        $from = $session->status;
        if ($from !== $to && ! $this->stateMachine->canTransition($from, $to)) {
            throw new AiAssistantException('Unsupported realtime session transition.', 'ai_realtime_unsupported_transition', 409);
        }

        $updates = [
            'status' => $to,
            'last_activity_at' => now(),
        ];

        if ($mutator !== null) {
            $updates = array_merge($updates, $mutator($session) ?: []);
        }

        $session->update($updates);
        $fresh = $session->fresh();

        $this->auditService->record($fresh, $eventType, $payload, $from, $to);

        return $fresh;
    }

    private function withLockedSession(AiRealtimeSession $session, callable $callback): AiRealtimeSession
    {
        return DB::transaction(function () use ($session, $callback) {
            /** @var AiRealtimeSession $locked */
            $locked = AiRealtimeSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            return $callback($locked);
        });
    }

    private function ensureRealtimeEnabled(): void
    {
        if (! config('ai_realtime.enabled', false)) {
            throw new AiAssistantException('Realtime backend is currently disabled.', 'ai_realtime_disabled', 503);
        }
    }

    private function ensureActiveSessionCapacity(User $user): void
    {
        $limit = (int) config('ai_realtime.sessions.max_active_sessions_per_user', 1);
        if ($limit <= 0) {
            return;
        }

        $active = AiRealtimeSession::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', AiRealtimeSession::terminalStates())
            ->count();

        if ($active >= $limit) {
            throw new AiAssistantException('Too many active realtime sessions for this user.', 'ai_realtime_session_limit_reached', 429);
        }
    }

    private function ensureBudgetAvailable(User $user): void
    {
        $limit = (int) config('ai_assistant.budgets.per_user_daily_tokens', 0);
        if ($limit <= 0) {
            return;
        }

        $used = (int) AIConversation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('total_tokens')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('total_tokens');

        if ($used >= $limit) {
            throw new AiAssistantException('AI daily budget exhausted for realtime session creation.', 'ai_budget_exceeded', 429);
        }
    }

    private function ensureUsable(AiRealtimeSession $session, bool $allowEnded = false): void
    {
        if (! $allowEnded && in_array($session->status, AiRealtimeSession::terminalStates(), true)) {
            throw new AiAssistantException('Realtime session has already ended.', 'ai_realtime_session_expired', 410);
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            throw new AiAssistantException('Realtime session expired.', 'ai_realtime_session_expired', 410);
        }
    }

    private function enforceLiveBudget(AiRealtimeSession $session): AiRealtimeSession
    {
        $sessionLimit = (int) config('ai_realtime.budgets.estimated_max_session_tokens', 0);
        $dailyLimit = (int) config('ai_assistant.budgets.per_user_daily_tokens', 0);

        $dailyUsed = (int) AIConversation::query()
            ->where('user_id', $session->user_id)
            ->whereNotNull('total_tokens')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('total_tokens');

        $realtimeUsed = (int) AiRealtimeSession::query()
            ->where('user_id', $session->user_id)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereKeyNot($session->id)
            ->sum('estimated_total_tokens');

        $aggregateUsed = $dailyUsed + $realtimeUsed + (int) $session->estimated_total_tokens;
        $sessionExceeded = $sessionLimit > 0 && (int) $session->estimated_total_tokens >= $sessionLimit;
        $dailyExceeded = $dailyLimit > 0 && $aggregateUsed >= $dailyLimit;

        $metadata = array_merge($session->metadata ?? [], [
            'budget' => [
                'session_limit_tokens' => $sessionLimit,
                'daily_limit_tokens' => $dailyLimit,
                'aggregate_used_tokens' => $aggregateUsed,
                'session_exceeded' => $sessionExceeded,
                'daily_exceeded' => $dailyExceeded,
                'reconciled_at' => now()->toIso8601String(),
            ],
        ]);

        $session->update(['metadata' => $metadata]);
        $fresh = $session->fresh();

        if (! $sessionExceeded && ! $dailyExceeded) {
            return $fresh;
        }

        $this->auditService->record($fresh, 'budget_exhausted', [
            'session_total_tokens' => $fresh->estimated_total_tokens,
            'aggregate_used_tokens' => $aggregateUsed,
            'session_limit_tokens' => $sessionLimit,
            'daily_limit_tokens' => $dailyLimit,
        ], $fresh->status, AiRealtimeSession::STATUS_ENDED, errorCode: 'ai_budget_exceeded');

        return $this->terminate($fresh, 'ai_budget_exceeded');
    }
}
