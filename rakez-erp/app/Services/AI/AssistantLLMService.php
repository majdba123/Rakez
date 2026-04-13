<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AssistantLLMService
{
    public function __construct(
        private readonly AiTextClientManager $textClient
    ) {}

    /**
     * Generate an answer using the LLM.
     *
     * @param string $systemPrompt The system instructions
     * @param array $knowledgeSnippets Array of knowledge entries (title, content_md)
     * @param string $userMessage The user's question
     * @param array $userContext User context (role, permissions, etc.)
     * @return array{answer: string, tokens: int|null, latency_ms: int|null}
     */
    public function generateAnswer(
        string $systemPrompt,
        array $knowledgeSnippets,
        string $userMessage,
        array $userContext
    ): array {
        $start = microtime(true);

        // Build instructions with knowledge snippets
        $instructions = $this->buildInstructions($systemPrompt, $knowledgeSnippets, $userContext);

        $messages = collect($userContext['history'] ?? [])
            ->map(fn (array $message) => [
                'role' => $message['role'],
                'content' => $message['content'],
            ])
            ->all();

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $metadata = [
            'capability' => 'assistant_help',
            'user_id' => $userContext['user_id'] ?? null,
            'service' => 'assistant_help',
            'session_id' => $userContext['conversation_id'] ?? null,
            'correlation_id' => $userContext['correlation_id'] ?? null,
        ];

        try {
            $response = $this->textClient->createResponse($instructions, $messages, $metadata);

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'answer' => $response->text,
                'tokens' => $response->totalTokens,
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            Log::error('AssistantLLMService.generateAnswer failed', [
                'error' => $e->getMessage(),
                'user_id' => $userContext['user_id'] ?? null,
            ]);

            return [
                'answer' => (string) config('ai_assistant.messages.assistant_llm_error', 'عذرًا، حدث خطأ أثناء المعالجة. يُرجى المحاولة لاحقًا.'),
                'tokens' => null,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }
    }

    /**
     * Build the full instructions string with knowledge snippets.
     */
    private function buildInstructions(string $systemPrompt, array $knowledgeSnippets, array $userContext): string
    {
        $lines = [
            'SYSTEM POLICY:',
            $systemPrompt,
            '',
            'USER CONTEXT:',
            '- Role: ' . ($userContext['role'] ?? 'unknown'),
            '- Module: ' . ($userContext['module'] ?? 'general'),
            '- Page: ' . ($userContext['page_key'] ?? 'none'),
            '',
        ];

        if (! empty($userContext['summary'])) {
            $lines[] = 'CONVERSATION SUMMARY:';
            $lines[] = $userContext['summary'];
            $lines[] = '';
        }

        if (!empty($knowledgeSnippets)) {
            $lines[] = 'KNOWLEDGE SNIPPETS (use only these to answer):';
            $lines[] = '---';

            foreach ($knowledgeSnippets as $index => $snippet) {
                $lines[] = sprintf('[%d] %s', $index + 1, $snippet['title']);
                $lines[] = $snippet['content_md'];
                $lines[] = '---';
            }
        } else {
            $lines[] = 'KNOWLEDGE SNIPPETS: None available for this context.';
            $lines[] = 'If the user asks about something not covered, explain that no relevant help content is available and suggest they contact an administrator.';
        }

        return implode("\n", $lines);
    }

}
