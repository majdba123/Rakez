<?php

namespace App\Services\AI;

use App\Events\AI\AiRequestCompleted;
use App\Models\AIConversation;
use App\Models\User;
use App\Services\AI\Data\AiTextResponse;
use App\Services\AI\Exceptions\AiAssistantDisabledException;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Exceptions\AiBudgetExceededException;
use App\Services\AI\Exceptions\AiUnauthorizedSectionException;
use App\Services\AI\Policy\RakizAiPolicyContextBuilder;
use Carbon\Carbon;
use Generator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIAssistantService
{
    public function __construct(
        private readonly CapabilityResolver $capabilityResolver,
        private readonly SectionRegistry $sectionRegistry,
        private readonly SystemPromptBuilder $promptBuilder,
        private readonly ContextBuilder $contextBuilder,
        private readonly AiTextClientManager $textClient,
        private readonly ContextValidator $contextValidator,
        private readonly AccessExplanationEngine $explanationEngine,
        private readonly AiAuditService $auditService,
        private readonly PromptVersionManager $promptVersionManager,
        private readonly RakizAiPolicyContextBuilder $rakizPolicyContext,
        private readonly ?RakizAiOrchestrator $orchestrator = null,
    ) {}

    public function ask(string $question, User $user, ?string $sectionKey = null, array $context = [], array $runtime = []): array
    {
        $this->ensureEnabled();

        // Fast-path: Access Explanation
        if ($explanation = $this->explanationEngine->explain($user, $question)) {
            return $this->buildExplanationResponse($explanation, $user, $sectionKey);
        }

        $capabilities = $this->capabilityResolver->resolve($user);

        if ($sectionKey && ! $this->isSectionAvailable($sectionKey, $capabilities)) {
            throw new AiUnauthorizedSectionException($sectionKey);
        }

        $this->ensureWithinBudget($user);

        $section = $this->sectionRegistry->find($sectionKey);
        $context = $this->filterContext($sectionKey, $context);
        $contextSummary = $this->contextBuilder->build($user, $sectionKey, $capabilities, $context);
        $promptVersion = $this->promptVersionManager->resolve(
            'assistant.system',
            $this->promptBuilder->build($user, $capabilities, $section, $contextSummary),
            $user->id
        );
        $instructions = $promptVersion['content'];

        $sessionId = (string) Str::uuid();
        $messages = [
            ['role' => 'user', 'content' => $question],
        ];

        $provider = $runtime['provider'] ?? null;
        $response = $this->textClient->createResponse($instructions, $messages, [
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'user_id' => $user->id,
            'service' => 'ai_assistant.ask',
            'correlation_id' => $runtime['correlation_id'] ?? null,
        ], [], $provider);

        $answer = $response->text;
        $errorCode = null;
        if ($answer === '') {
            $answer = (string) config('ai_assistant.messages.empty_response', 'تعذّر إنشاء ردّ الآن. يُرجى إعادة المحاولة.');
            $errorCode = 'empty_response';
        }

        $this->storeMessage($user, $sessionId, 'user', $question, $sectionKey, [
            'context' => $context,
            'capabilities' => $capabilities,
            'prompt_version_id' => $promptVersion['version_id'],
        ]);
        $assistantMessage = $this->storeAssistantMessage($user, $sessionId, $answer, $sectionKey, $response);

        $payload = [
            'message' => $answer,
            'session_id' => $sessionId,
            'conversation_id' => $assistantMessage->id,
            'error_code' => $errorCode,
            'meta' => $this->buildMeta($assistantMessage, $sessionId, $sectionKey),
        ];

        $this->logResponse($user, $sectionKey, $sessionId, $assistantMessage);
        $this->recordAudit($user, 'query', 'assistant_session', $assistantMessage->id, [
            'question' => $question,
            'section' => $sectionKey,
        ], $payload);
        $this->dispatchCompletedEvent($user, $sessionId, $sectionKey, 'ask', $assistantMessage, 0);

        return $payload;
    }

    public function chat(string $message, User $user, ?string $sessionId, ?string $sectionKey = null, array $context = [], array $runtime = []): array
    {
        $this->ensureEnabled();

        // Fast-path: Access Explanation
        if ($explanation = $this->explanationEngine->explain($user, $message)) {
            return $this->buildExplanationResponse($explanation, $user, $sectionKey, $sessionId, $runtime);
        }

        $capabilities = $this->capabilityResolver->resolve($user);

        if ($sectionKey && ! $this->isSectionAvailable($sectionKey, $capabilities)) {
            throw new AiUnauthorizedSectionException($sectionKey);
        }

        $this->ensureWithinBudget($user);

        $section = $this->sectionRegistry->find($sectionKey);
        $context = $this->filterContext($sectionKey, $context);
        $contextSummary = $this->contextBuilder->build($user, $sectionKey, $capabilities, $context);

        $sessionId = $sessionId ?: (string) Str::uuid();

        $historyWindow = (int) config('ai_assistant.chat.tail_messages', 6);
        $recentMessages = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_summary', false)
            ->orderByDesc('created_at')
            ->limit($historyWindow)
            ->get()
            ->reverse()
            ->values();

        $summary = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_summary', true)
            ->orderByDesc('created_at')
            ->first();

        $promptVersion = $this->promptVersionManager->resolve(
            'assistant.system',
            $this->promptBuilder->build($user, $capabilities, $section, $contextSummary),
            $user->id
        );
        $instructions = $promptVersion['content'];
        if ($summary) {
            $summaryLabel = (string) config('ai_assistant.messages.conversation_summary_label', 'ملخص المحادثة:');
            $instructions .= "\n\n{$summaryLabel}\n".$summary->message;
        }

        $messages = $recentMessages->map(fn (AIConversation $entry) => [
            'role' => $entry->role,
            'content' => $entry->message,
        ])->toArray();

        $messages[] = ['role' => 'user', 'content' => $message];

        if ($this->shouldUseOrchestrator($user, $sectionKey, $message)) {
            return $this->chatWithOrchestrator($message, $user, $sessionId, $sectionKey, $context, $capabilities, $promptVersion);
        }

        $provider = $runtime['provider'] ?? null;
        $response = $this->textClient->createResponse($instructions, $messages, [
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'user_id' => $user->id,
            'service' => 'ai_assistant.chat',
            'correlation_id' => $runtime['correlation_id'] ?? null,
        ], [], $provider);

        $answer = $response->text;
        $errorCode = null;
        if ($answer === '') {
            $answer = (string) config('ai_assistant.messages.empty_response', 'تعذّر إنشاء ردّ الآن. يُرجى إعادة المحاولة.');
            $errorCode = 'empty_response';
        }

        $this->storeMessage($user, $sessionId, 'user', $message, $sectionKey, [
            'context' => $context,
            'capabilities' => $capabilities,
            'prompt_version_id' => $promptVersion['version_id'],
        ]);
        $assistantMessage = $this->storeAssistantMessage(
            $user,
            $sessionId,
            $answer,
            $sectionKey,
            $response,
            $runtime['correlation_id'] ?? null
        );

        $this->summarizeConversationIfNeeded($user, $sessionId);

        $payload = [
            'message' => $answer,
            'session_id' => $sessionId,
            'conversation_id' => $assistantMessage->id,
            'error_code' => $errorCode,
            'meta' => $this->buildMeta($assistantMessage, $sessionId, $sectionKey),
        ];

        $this->logResponse($user, $sectionKey, $sessionId, $assistantMessage);
        $this->recordAudit($user, 'query', 'assistant_session', $assistantMessage->id, [
            'message' => $message,
            'section' => $sectionKey,
            'session_id' => $sessionId,
        ], $payload);
        $this->dispatchCompletedEvent($user, $sessionId, $sectionKey, 'chat', $assistantMessage, 0);

        return $payload;
    }

    /**
     * Streaming variant of chat(). Yields SSE-formatted lines.
     *
     * Each yield is a complete SSE line: "data: {...}\n\n"
     * Final yield is "data: [DONE]\n\n".
     * After the generator is consumed, the full answer has been stored in DB.
     *
     * @return Generator<int, string>
     */
    /**
     * Hybrid streaming chat. Tries SSE streaming first; if that fails,
     * falls back to the synchronous createResponse() and sends the
     * full reply as a single SSE chunk. The caller always gets valid
     * SSE regardless of whether OpenAI streaming is available.
     *
     * @return Generator<int, string>
     */
    public function streamChat(string $message, User $user, ?string $sessionId, ?string $sectionKey = null, array $context = [], array $runtime = []): Generator
    {
        $this->ensureEnabled();

        // Fast-path: Access Explanation (non-streamable, send as single chunk)
        if ($explanation = $this->explanationEngine->explain($user, $message)) {
            $payload = $this->buildExplanationResponse($explanation, $user, $sectionKey, $sessionId);
            yield 'data: '.json_encode(['chunk' => $payload['message']], JSON_UNESCAPED_UNICODE)."\n\n";
            yield 'data: '.json_encode(['session_id' => $payload['session_id'], 'done' => true])."\n\n";
            yield "data: [DONE]\n\n";

            return;
        }

        $capabilities = $this->capabilityResolver->resolve($user);

        if ($sectionKey && ! $this->isSectionAvailable($sectionKey, $capabilities)) {
            throw new AiUnauthorizedSectionException($sectionKey);
        }

        $this->ensureWithinBudget($user);

        $section = $this->sectionRegistry->find($sectionKey);
        $context = $this->filterContext($sectionKey, $context);
        $contextSummary = $this->contextBuilder->build($user, $sectionKey, $capabilities, $context);

        $sessionId = $sessionId ?: (string) Str::uuid();

        $historyWindow = (int) config('ai_assistant.chat.tail_messages', 6);
        $recentMessages = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_summary', false)
            ->orderByDesc('created_at')
            ->limit($historyWindow)
            ->get()
            ->reverse()
            ->values();

        $summary = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_summary', true)
            ->orderByDesc('created_at')
            ->first();

        $promptVersion = $this->promptVersionManager->resolve(
            'assistant.system',
            $this->promptBuilder->build($user, $capabilities, $section, $contextSummary),
            $user->id
        );
        $instructions = $promptVersion['content'];
        if ($summary) {
            $summaryLabel = (string) config('ai_assistant.messages.conversation_summary_label', 'ملخص المحادثة:');
            $instructions .= "\n\n{$summaryLabel}\n".$summary->message;
        }

        $messages = $recentMessages->map(fn (AIConversation $entry) => [
            'role' => $entry->role,
            'content' => $entry->message,
        ])->toArray();

        $messages[] = ['role' => 'user', 'content' => $message];

        if ($this->shouldUseOrchestrator($user, $sectionKey, $message)) {
            yield from $this->streamChatWithOrchestrator(
                $message,
                $user,
                $sessionId,
                $sectionKey,
                $context,
                $capabilities,
                $promptVersion,
                $runtime
            );

            return;
        }

        $this->storeMessage($user, $sessionId, 'user', $message, $sectionKey, [
            'context' => $context,
            'capabilities' => $capabilities,
            'prompt_version_id' => $promptVersion['version_id'],
        ]);

        $metadata = [
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'user_id' => $user->id,
            'service' => 'ai_assistant.stream',
            'correlation_id' => $runtime['correlation_id'] ?? null,
        ];
        $provider = $runtime['provider'] ?? null;

        $fullAnswer = '';
        $streamed = false;

        // ── Try streaming first ──
        try {
            foreach ($this->textClient->createStreamedResponse($instructions, $messages, $metadata, [], $provider) as $delta) {
                $fullAnswer .= $delta;
                $streamed = true;
                yield 'data: '.json_encode(['chunk' => $delta], JSON_UNESCAPED_UNICODE)."\n\n";
            }
        } catch (\Throwable $streamException) {
            Log::warning('ai.assistant.stream_fallback', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $streamException->getMessage(),
                'partial_length' => strlen($fullAnswer),
            ]);

            // ── Fallback: synchronous full response ──
            if (! $streamed) {
                try {
                    $response = $this->textClient->createResponse($instructions, $messages, $metadata, [], $provider);
                    $fullAnswer = $response->text;

                    if ($fullAnswer === '') {
                        $fullAnswer = (string) config('ai_assistant.messages.empty_response', 'تعذّر إنشاء ردّ الآن. يُرجى إعادة المحاولة.');
                    }

                    yield 'data: '.json_encode(['chunk' => $fullAnswer], JSON_UNESCAPED_UNICODE)."\n\n";

                    $assistantMsg = $this->storeAssistantMessage($user, $sessionId, $fullAnswer, $sectionKey, $response);
                    $this->summarizeConversationIfNeeded($user, $sessionId);

                    $this->logResponse($user, $sectionKey, $sessionId, $assistantMsg);

                    yield 'data: '.json_encode([
                        'session_id' => $sessionId,
                        'conversation_id' => $assistantMsg->id,
                        'done' => true,
                    ])."\n\n";
                    yield "data: [DONE]\n\n";

                    return;
                } catch (\Throwable $fallbackException) {
                    Log::error('ai.assistant.stream_fallback_failed', [
                        'user_id' => $user->id,
                        'session_id' => $sessionId,
                        'error' => $fallbackException->getMessage(),
                    ]);
                    throw $fallbackException;
                }
            }
            // If we already streamed partial data, just continue with what we have
        }

        if ($fullAnswer === '') {
            $fullAnswer = (string) config('ai_assistant.messages.empty_response', 'تعذّر إنشاء ردّ الآن. يُرجى إعادة المحاولة.');
            yield 'data: '.json_encode(['chunk' => $fullAnswer], JSON_UNESCAPED_UNICODE)."\n\n";
        }

        $assistantMsg = AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $fullAnswer,
            'section' => $sectionKey,
            'metadata' => [
                'streamed' => $streamed,
                'provider' => $this->textClient->resolveProvider($provider),
                'correlation_id' => $runtime['correlation_id'] ?? null,
            ],
            'model' => $this->textClient->defaultModelFor($provider),
        ]);

        $this->summarizeConversationIfNeeded($user, $sessionId);

        Log::info('ai.assistant.stream_response', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'model' => $assistantMsg->model,
            'answer_length' => strlen($fullAnswer),
            'streamed' => $streamed,
        ]);

        yield 'data: '.json_encode([
            'session_id' => $sessionId,
            'conversation_id' => $assistantMsg->id,
            'done' => true,
        ])."\n\n";
        yield "data: [DONE]\n\n";
    }

    /**
     * Tool-mode streaming currently emits a single final assistant chunk so that
     * the streaming route respects the same policy/tool path as sync chat
     * without introducing parallel or partial tool-call turns.
     *
     * @return Generator<int, string>
     */
    private function streamChatWithOrchestrator(
        string $message,
        User $user,
        string $sessionId,
        ?string $sectionKey,
        array $context,
        array $capabilities,
        array $promptVersion,
        array $runtime = [],
    ): Generator {
        $payload = $this->chatWithOrchestrator(
            $message,
            $user,
            $sessionId,
            $sectionKey,
            $context,
            $capabilities,
            $promptVersion,
            $runtime
        );

        yield 'data: '.json_encode([
            'chunk' => $payload['message'],
            'tool_mode' => true,
        ], JSON_UNESCAPED_UNICODE)."\n\n";

        yield 'data: '.json_encode([
            'session_id' => $payload['session_id'],
            'conversation_id' => $payload['conversation_id'],
            'done' => true,
            'tool_mode' => true,
            'sources' => $payload['sources'] ?? [],
            'links' => $payload['links'] ?? [],
            'suggestions' => $payload['suggestions'] ?? [],
            'meta' => $payload['meta'] ?? [],
        ], JSON_UNESCAPED_UNICODE)."\n\n";

        yield "data: [DONE]\n\n";
    }

    private function chatWithOrchestrator(
        string $message,
        User $user,
        string $sessionId,
        ?string $sectionKey,
        array $context,
        array $capabilities,
        array $promptVersion,
        array $runtime = [],
    ): array {
        if (! $this->orchestrator) {
            throw new AiAssistantException('AI tool orchestration is unavailable.', 'ai_provider_failed', 500);
        }

        $this->storeMessage($user, $sessionId, 'user', $message, $sectionKey, [
            'context' => $context,
            'capabilities' => $capabilities,
            'prompt_version_id' => $promptVersion['version_id'],
            'tool_mode' => true,
        ]);

        $section = (string) ($sectionKey ?? 'general');
        $policySnapshot = $this->rakizPolicyContext->buildDeterministicPolicySnapshot($user, $message, $section);
        $early = $this->rakizPolicyContext->earlyPolicyGateResponse($user, $message, $section, $policySnapshot);
        if ($early !== null) {
            $result = $early;
            $execMeta = [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'request_id' => null,
                'correlation_id' => (string) Str::uuid(),
                'latency_ms' => 0,
                'model' => 'policy_gate',
            ];
        } else {
            $pageContext = array_merge($context, [
                'section' => $section,
                'policy_snapshot' => $policySnapshot,
            ]);
            $result = $this->orchestrator->chat($user, $message, $sessionId, array_merge($pageContext, [
                'provider' => $runtime['provider'] ?? null,
            ]));
            $result = $this->rakizPolicyContext->applySnapshotNormalization($result, $policySnapshot);
            $execMeta = $result['_execution_meta'] ?? [];
        }

        unset($result['_execution_meta']);

        $assistantMessage = AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $result['answer_markdown'] ?? 'No answer generated.',
            'section' => $sectionKey,
            'metadata' => [
                'tool_mode' => true,
                'sources' => $result['sources'] ?? [],
                'links' => $result['links'] ?? [],
                'follow_up_questions' => $result['follow_up_questions'] ?? [],
                'access_notes' => $result['access_notes'] ?? [],
                'correlation_id' => $execMeta['correlation_id'] ?? null,
                'provider' => $execMeta['provider'] ?? ($runtime['provider'] ?? null),
            ],
            'model' => $execMeta['model'] ?? config('ai_assistant.v2.openai.model', config('ai_assistant.openai.model', 'gpt-4.1-mini')),
            'prompt_tokens' => $execMeta['prompt_tokens'] ?? null,
            'completion_tokens' => $execMeta['completion_tokens'] ?? null,
            'total_tokens' => $execMeta['total_tokens'] ?? null,
            'latency_ms' => $execMeta['latency_ms'] ?? null,
            'request_id' => $execMeta['request_id'] ?? null,
        ]);

        $payload = [
            'message' => $assistantMessage->message,
            'session_id' => $sessionId,
            'conversation_id' => $assistantMessage->id,
            'error_code' => null,
            'links' => $result['links'] ?? [],
            'sources' => $result['sources'] ?? [],
            'suggestions' => $result['follow_up_questions'] ?? [],
            'meta' => $this->buildMeta($assistantMessage, $sessionId, $sectionKey, ['tool_mode' => true]),
        ];

        $this->recordAudit($user, 'tool_query', 'assistant_session', $assistantMessage->id, [
            'message' => $message,
            'section' => $sectionKey,
        ], $payload);

        $this->dispatchCompletedEvent($user, $sessionId, $sectionKey, 'chat', $assistantMessage, 0);

        return $payload;
    }

    public function listSessions(User $user, ?string $section = null, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEnabled();
        $latestIds = AIConversation::query()
            ->select(DB::raw('MAX(id) as id'))
            ->where('user_id', $user->id)
            ->when($section, fn ($query) => $query->where('section', $section))
            ->groupBy('session_id');

        return AIConversation::query()
            ->whereIn('id', $latestIds)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function deleteSession(User $user, string $sessionId): int
    {
        $this->ensureEnabled();

        $exists = AIConversation::query()
            ->where('session_id', $sessionId)
            ->exists();

        if (! $exists) {
            return 0;
        }

        $owned = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->exists();

        if (! $owned) {
            throw new \App\Services\AI\Exceptions\AiAssistantException(
                'You do not have permission to delete this conversation.',
                'UNAUTHORIZED_SESSION_ACCESS',
                403
            );
        }

        return AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->delete();
    }

    public function availableSections(User $user): array
    {
        $this->ensureEnabled();
        $capabilities = $this->capabilityResolver->resolve($user);

        return $this->sectionRegistry->availableFor($capabilities);
    }

    public function suggestions(?string $sectionKey): array
    {
        return $this->sectionRegistry->suggestions($sectionKey);
    }

    private function isSectionAvailable(string $sectionKey, array $capabilities): bool
    {
        $section = $this->sectionRegistry->find($sectionKey);
        if (! $section) {
            return false;
        }

        $required = $section['required_capabilities'] ?? [];
        if (empty($required)) {
            return true;
        }

        return empty(array_diff($required, $capabilities));
    }

    private function storeMessage(User $user, string $sessionId, string $role, string $message, ?string $section, array $metadata): AIConversation
    {
        return AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => $role,
            'message' => $message,
            'section' => $section,
            'metadata' => $metadata,
        ]);
    }

    private function storeAssistantMessage(
        User $user,
        string $sessionId,
        string $message,
        ?string $section,
        AiTextResponse $response,
        ?string $fallbackCorrelationId = null,
    ): AIConversation
    {
        $correlationId = $response->correlationId ?? $fallbackCorrelationId;

        return AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $message,
            'section' => $section,
            'metadata' => [
                'provider_response_id' => $response->responseId,
                'correlation_id' => $correlationId,
                'provider' => $response->provider,
            ],
            'model' => $response->model,
            'prompt_tokens' => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'total_tokens' => $response->totalTokens,
            'latency_ms' => $response->latencyMs,
            'request_id' => $response->requestId,
        ]);
    }

    private function logResponse(User $user, ?string $sectionKey, string $sessionId, AIConversation $assistantMessage): void
    {
        Log::info('ai.assistant.response', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'model' => $assistantMessage->model,
            'prompt_tokens' => $assistantMessage->prompt_tokens,
            'completion_tokens' => $assistantMessage->completion_tokens,
            'total_tokens' => $assistantMessage->total_tokens,
            'latency_ms' => $assistantMessage->latency_ms,
            'request_id' => $assistantMessage->request_id,
        ]);
    }

    private function filterContext(?string $sectionKey, array $context): array
    {
        $allowed = $this->sectionRegistry->allowedContextParams($sectionKey);
        if (empty($allowed)) {
            return [];
        }

        $filtered = Arr::only($context, $allowed);

        // Validate context parameters using schema
        try {
            return $this->contextValidator->validate($sectionKey, $filtered);
        } catch (\InvalidArgumentException $e) {
            throw new AiAssistantException($e->getMessage(), 'ai_validation_failed', 422);
        }
    }

    private function summarizeConversationIfNeeded(User $user, string $sessionId): void
    {
        $summaryEvery = (int) config('ai_assistant.chat.summary_every', 12);
        $summaryWindow = (int) config('ai_assistant.chat.summary_window', 12);

        $count = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_summary', false)
            ->count();

        if ($count === 0 || $count % $summaryEvery !== 0) {
            return;
        }

        $recent = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_summary', false)
            ->orderByDesc('created_at')
            ->limit($summaryWindow)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AIConversation $entry) => [
                'role' => $entry->role,
                'content' => $entry->message,
            ])
            ->toArray();

        $instructions = implode("\n", [
            'Summarize the conversation so far for future context.',
            'Include key facts, user goals, and any pending questions.',
            'Keep it concise and safe for internal use.',
        ]);

        $response = $this->textClient->createResponse($instructions, $recent, [
            'session_id' => $sessionId,
            'summary' => 'true',
            'service' => 'ai_assistant.summary',
        ]);

        AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $response->text,
            'section' => null,
            'metadata' => [
                'summary' => true,
                'provider' => $response->provider,
                'correlation_id' => $response->correlationId,
            ],
            'model' => $response->model,
            'prompt_tokens' => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'total_tokens' => $response->totalTokens,
            'latency_ms' => $response->latencyMs,
            'request_id' => $response->requestId,
            'is_summary' => true,
        ]);
    }

    private function ensureEnabled(): void
    {
        if (! config('ai_assistant.enabled')) {
            throw new AiAssistantDisabledException;
        }
    }

    public function ensureBudgetAvailable(User $user): void
    {
        $this->ensureEnabled();
        $this->ensureWithinBudget($user);
    }

    private function ensureWithinBudget(User $user): void
    {
        $limit = (int) config('ai_assistant.budgets.per_user_daily_tokens', 0);
        if ($limit <= 0) {
            return;
        }

        $start = Carbon::now()->startOfDay();
        $used = (int) AIConversation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('total_tokens')
            ->where('created_at', '>=', $start)
            ->sum('total_tokens');

        if ($used >= $limit) {
            throw new AiBudgetExceededException($limit, $used);
        }
    }

    private function buildExplanationResponse(array $explanation, User $user, ?string $sectionKey, ?string $sessionId = null, array $runtime = []): array
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $correlationId = $runtime['correlation_id'] ?? null;

        // Store the user message
        $this->storeMessage($user, $sessionId, 'user', 'Access Explanation Request', $sectionKey, [
            'explanation_result' => $explanation,
            'correlation_id' => $correlationId,
        ]);

        return [
            'message' => $explanation['message'],
            'session_id' => $sessionId,
            'conversation_id' => null,
            'steps' => $explanation['steps'],
            'access_summary' => [
                'allowed' => $explanation['allowed'],
                'reason_code' => $explanation['reason_code'],
            ],
            'meta' => [
                'session_id' => $sessionId,
                'section' => $sectionKey,
                'tokens' => 0,
                'latency_ms' => 0,
                'model' => 'deterministic_access_explainer',
                'request_id' => null,
                'correlation_id' => $correlationId,
            ],
        ];
    }

    private function buildMeta(AIConversation $assistantMessage, string $sessionId, ?string $sectionKey, array $extra = []): array
    {
        return array_merge([
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'tokens' => $assistantMessage->total_tokens,
            'latency_ms' => $assistantMessage->latency_ms,
            'model' => $assistantMessage->model,
            'request_id' => $assistantMessage->request_id,
            'correlation_id' => $assistantMessage->metadata['correlation_id'] ?? null,
            'provider' => $assistantMessage->metadata['provider'] ?? null,
        ], $extra);
    }

    private function recordAudit(
        User $user,
        string $action,
        ?string $resourceType,
        ?int $resourceId,
        array $input,
        array $output,
    ): void {
        $this->auditService->record($user, $action, $resourceType, $resourceId, $input, $output);
    }

    private function dispatchCompletedEvent(
        User $user,
        ?string $sessionId,
        ?string $sectionKey,
        string $requestType,
        AIConversation $assistantMessage,
        int $toolCallsCount,
    ): void {
        event(new AiRequestCompleted(
            userId: $user->id,
            sessionId: $sessionId,
            section: $sectionKey,
            requestType: $requestType,
            model: $assistantMessage->model ?? $this->textClient->defaultModelFor(),
            promptTokens: (int) ($assistantMessage->prompt_tokens ?? 0),
            completionTokens: (int) ($assistantMessage->completion_tokens ?? 0),
            totalTokens: (int) ($assistantMessage->total_tokens ?? 0),
            latencyMs: (float) ($assistantMessage->latency_ms ?? 0),
            toolCallsCount: $toolCallsCount,
            correlationId: $assistantMessage->metadata['correlation_id'] ?? null,
        ));
    }

    private function shouldUseOrchestrator(User $user, ?string $sectionKey, string $message): bool
    {
        if (! $this->orchestrator || ! config('ai_assistant.tools.orchestrated_chat', true)) {
            return false;
        }

        if (! $user->can('use-ai-assistant')) {
            return false;
        }

        $toolSections = config('ai_assistant.tools.sections', []);
        if ($sectionKey && in_array($sectionKey, $toolSections, true)) {
            return true;
        }

        return (bool) preg_match('/\b(kpi|campaign|roi|romi|lead|contract|call|budget|analytics)\b/i', $message);
    }
}
