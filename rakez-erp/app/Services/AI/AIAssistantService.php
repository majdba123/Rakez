<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\AiAssistantDisabledException;
use App\Services\AI\Exceptions\AiBudgetExceededException;
use App\Services\AI\Exceptions\AiUnauthorizedSectionException;
use App\Models\AIConversation;
use App\Models\User;
use Carbon\Carbon;
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
        private readonly OpenAIResponsesClient $openAIClient,
        private readonly ContextValidator $contextValidator
    ) {}

    public function ask(string $question, User $user, ?string $sectionKey = null, array $context = []): array
    {
        $this->ensureEnabled();
        
        $capabilities = $this->capabilityResolver->resolve($user);

        if ($sectionKey && ! $this->isSectionAvailable($sectionKey, $capabilities)) {
            throw new AiUnauthorizedSectionException($sectionKey);
        }

        $this->ensureWithinBudget($user);

        $section = $this->sectionRegistry->find($sectionKey);
        $context = $this->filterContext($sectionKey, $context);
        $contextSummary = $this->contextBuilder->build($user, $sectionKey, $capabilities, $context);
        $instructions = $this->promptBuilder->build($user, $capabilities, $section, $contextSummary);

        $sessionId = (string) Str::uuid();
        $messages = [
            ['role' => 'user', 'content' => $question],
        ];

        $response = $this->openAIClient->createResponse($instructions, $messages, [
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'user_id' => $user->id,
        ]);

        $answer = $this->extractAssistantReply($response);
        $errorCode = null;
        if ($answer === '') {
            $answer = 'I could not generate a response. Please try again.';
            $errorCode = 'empty_response';
        }

        $this->storeMessage($user, $sessionId, 'user', $question, $sectionKey, [
            'context' => $context,
            'capabilities' => $capabilities,
        ]);
        $assistantMessage = $this->storeAssistantMessage($user, $sessionId, $answer, $sectionKey, $response);

        $payload = [
            'message' => $answer,
            'session_id' => $sessionId,
            'conversation_id' => $assistantMessage->id,
            'error_code' => $errorCode,
        ];

        $this->logResponse($user, $sectionKey, $sessionId, $assistantMessage);

        return $payload;
    }

    public function chat(string $message, User $user, ?string $sessionId, ?string $sectionKey = null, array $context = []): array
    {
        $this->ensureEnabled();
        
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

        $instructions = $this->promptBuilder->build($user, $capabilities, $section, $contextSummary);
        if ($summary) {
            $instructions .= "\n\nConversation summary:\n" . $summary->message;
        }

        $messages = $recentMessages->map(fn (AIConversation $entry) => [
            'role' => $entry->role,
            'content' => $entry->message,
        ])->toArray();

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->openAIClient->createResponse($instructions, $messages, [
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'user_id' => $user->id,
        ]);

        $answer = $this->extractAssistantReply($response);
        $errorCode = null;
        if ($answer === '') {
            $answer = 'I could not generate a response. Please try again.';
            $errorCode = 'empty_response';
        }

        $this->storeMessage($user, $sessionId, 'user', $message, $sectionKey, [
            'context' => $context,
            'capabilities' => $capabilities,
        ]);
        $assistantMessage = $this->storeAssistantMessage($user, $sessionId, $answer, $sectionKey, $response);

        $this->summarizeConversationIfNeeded($user, $sessionId);

        $payload = [
            'message' => $answer,
            'session_id' => $sessionId,
            'conversation_id' => $assistantMessage->id,
            'error_code' => $errorCode,
        ];

        $this->logResponse($user, $sectionKey, $sessionId, $assistantMessage);

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
            
        if (!$exists) {
            return 0;
        }

        $owned = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->exists();

        if (!$owned) {
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

    private function extractAssistantReply($response): string
    {
        if (isset($response->output) && is_array($response->output)) {
            foreach ($response->output as $output) {
                if (($output->type ?? '') === 'message' && ($output->role ?? '') === 'assistant') {
                    if (isset($output->content) && is_array($output->content)) {
                        foreach ($output->content as $content) {
                            if (($content->type ?? '') === 'output_text') {
                                return $content->text ?? '';
                            }
                        }
                    }
                }
            }
        }

        return $response->outputText ?? '';
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

    private function storeAssistantMessage(User $user, string $sessionId, string $message, ?string $section, $response): AIConversation
    {
        $usage = $response->usage;
        $meta = $response->meta();

        return AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $message,
            'section' => $section,
            'metadata' => [
                'response_id' => $response->id,
            ],
            'model' => $response->model,
            'prompt_tokens' => $usage?->inputTokens,
            'completion_tokens' => $usage?->outputTokens,
            'total_tokens' => $usage?->totalTokens,
            'latency_ms' => $meta->openai->processingMs ?? null,
            'request_id' => $meta->requestId ?? null,
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
            // If validation fails, return empty array (safe fallback)
            return [];
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

        $response = $this->openAIClient->createResponse($instructions, $recent, [
            'session_id' => $sessionId,
            'summary' => 'true',
        ]);

        AIConversation::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $response->outputText ?? '',
            'section' => null,
            'metadata' => ['summary' => true],
            'model' => $response->model,
            'prompt_tokens' => $response->usage?->inputTokens,
            'completion_tokens' => $response->usage?->outputTokens,
            'total_tokens' => $response->usage?->totalTokens,
            'latency_ms' => $response->meta()->openai->processingMs ?? null,
            'request_id' => $response->meta()->requestId ?? null,
            'is_summary' => true,
        ]);
    }

    private function ensureEnabled(): void
    {
        if (! config('ai_assistant.enabled')) {
            throw new AiAssistantDisabledException();
        }
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
}
