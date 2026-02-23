<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\AssistantChatRequest;
use App\Models\AssistantConversation;
use App\Models\AssistantKnowledgeEntry;
use App\Models\AssistantMessage;
use App\Models\AssistantPrompt;
use App\Services\AI\AssistantLLMService;
use Illuminate\Http\JsonResponse;

class AssistantChatController extends Controller
{
    public function __construct(
        private readonly AssistantLLMService $llmService
    ) {}

    /**
     * Handle a chat request from the AI assistant.
     */
    public function chat(AssistantChatRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('use-ai-assistant')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to use the AI assistant.',
            ], 403);
        }

        $message = $request->input('message');
        $module = $request->input('module');
        $pageKey = $request->input('page_key');
        $language = $request->input('language', 'ar');
        $conversationId = $request->input('conversation_id');

        // Get or create conversation
        $conversation = $this->getOrCreateConversation($user, $conversationId, [
            'module' => $module,
            'page_key' => $pageKey,
            'language' => $language,
        ]);

        // Log user message
        $this->logMessage($conversation->id, 'user', $message);

        // Fetch allowed knowledge entries
        $knowledgeEntries = $this->fetchAllowedKnowledge($user, $module, $pageKey, $language);

        // Build system prompt
        $systemPrompt = $this->buildSystemPrompt($language);

        // Prepare knowledge snippets for LLM
        $knowledgeSnippets = $knowledgeEntries->map(fn ($entry) => [
            'title' => $entry->title,
            'content_md' => $entry->content_md,
        ])->toArray();

        // Prepare user context
        $userContext = [
            'user_id' => $user->id,
            'role' => $user->getRoleNames()->first() ?? 'user',
            'module' => $module,
            'page_key' => $pageKey,
        ];

        // Generate answer
        $result = $this->llmService->generateAnswer(
            $systemPrompt,
            $knowledgeSnippets,
            $message,
            $userContext
        );

        // Log assistant message
        $this->logMessage(
            $conversation->id,
            'assistant',
            $result['answer'],
            'assistant_help',
            $result['tokens'],
            $result['latency_ms']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'reply' => $result['answer'],
                'knowledge_used_count' => count($knowledgeSnippets),
            ],
        ]);
    }

    /**
     * Get existing conversation or create a new one.
     */
    private function getOrCreateConversation($user, ?int $conversationId, array $context): AssistantConversation
    {
        if ($conversationId) {
            $conversation = AssistantConversation::query()
                ->where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return AssistantConversation::create([
            'user_id' => $user->id,
            'context' => $context,
        ]);
    }

    /**
     * Log a message to the conversation.
     */
    private function logMessage(
        int $conversationId,
        string $role,
        string $content,
        ?string $capabilityUsed = null,
        ?int $tokens = null,
        ?int $latencyMs = null
    ): AssistantMessage {
        return AssistantMessage::create([
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'capability_used' => $capabilityUsed,
            'tokens' => $tokens,
            'latency_ms' => $latencyMs,
        ]);
    }

    /**
     * Fetch knowledge entries allowed for this user.
     */
    private function fetchAllowedKnowledge($user, ?string $module, ?string $pageKey, string $language)
    {
        $maxSnippets = config('assistant.max_knowledge_snippets', 20);

        return AssistantKnowledgeEntry::query()
            ->active()
            ->where('language', $language)
            ->forPage($module, $pageKey)
            ->visibleToUser($user)
            ->orderBy('priority', 'asc')
            ->limit($maxSnippets)
            ->get();
    }

    /**
     * Build the system prompt from DB or use default.
     */
    private function buildSystemPrompt(string $language): string
    {
        // Try to get custom prompts from DB
        $systemPolicy = AssistantPrompt::getByKey('system_policy', $language);
        $styleGuide = AssistantPrompt::getByKey('style_guide', $language);
        $refusalPolicy = AssistantPrompt::getByKey('refusal_policy', $language);

        $parts = [];

        if ($systemPolicy) {
            $parts[] = $systemPolicy->content_md;
        } else {
            $parts[] = config('assistant.default_system_policy');
        }

        if ($styleGuide) {
            $parts[] = "\nSTYLE GUIDE:\n" . $styleGuide->content_md;
        }

        if ($refusalPolicy) {
            $parts[] = "\nREFUSAL POLICY:\n" . $refusalPolicy->content_md;
        }

        return implode("\n", $parts);
    }
}

