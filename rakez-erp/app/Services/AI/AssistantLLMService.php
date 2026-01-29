<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AssistantLLMService
{
    public function __construct(
        private readonly OpenAIResponsesClient $openAIClient
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

        $messages = [
            ['role' => 'user', 'content' => $userMessage],
        ];

        $metadata = [
            'capability' => 'assistant_help',
            'user_id' => $userContext['user_id'] ?? null,
        ];

        try {
            $response = $this->openAIClient->createResponse($instructions, $messages, $metadata);

            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $answer = $this->extractAnswer($response);

            return [
                'answer' => $answer,
                'tokens' => $response->usage?->totalTokens,
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            Log::error('AssistantLLMService.generateAnswer failed', [
                'error' => $e->getMessage(),
                'user_id' => $userContext['user_id'] ?? null,
            ]);

            return [
                'answer' => 'I apologize, but I encountered an error while processing your request. Please try again later.',
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

    /**
     * Extract the answer text from the OpenAI response.
     */
    private function extractAnswer($response): string
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

        return $response->outputText ?? 'I was unable to generate a response.';
    }
}

