<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class OpenAIEmbeddingClient
{
    public function embed(string $text): array
    {
        $model = config('ai_assistant.v2.openai.embed_model', 'text-embedding-3-small');
        $text = $this->redactSecrets($text);
        if (strlen($text) > 8000) {
            $text = mb_substr($text, 0, 8000);
        }

        try {
            $response = $this->withRetry(fn () => OpenAI::embeddings()->create([
                'model' => $model,
                'input' => $text,
            ]));

            $embedding = $response->embeddings[0]->embedding ?? [];

            return is_array($embedding) ? $embedding : [];
        } catch (Throwable $e) {
            Log::warning('OpenAI embedding failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Embed multiple texts in one request (batch).
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }
        $model = config('ai_assistant.v2.openai.embed_model', 'text-embedding-3-small');
        $inputs = array_map(fn ($t) => $this->redactSecrets(mb_substr($t, 0, 8000)), $texts);

        try {
            $response = $this->withRetry(fn () => OpenAI::embeddings()->create([
                'model' => $model,
                'input' => $inputs,
            ]));

            $out = [];
            foreach ($response->embeddings as $i => $emb) {
                $out[$i] = is_array($emb->embedding) ? $emb->embedding : [];
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('OpenAI embeddings batch failed', ['error' => $e->getMessage()]);

            return array_fill(0, count($texts), []);
        }
    }

    private function redactSecrets(string $text): string
    {
        $patterns = [
            '/\b(?:sk-[a-zA-Z0-9]{20,})\b/',
            '/\b(?:password|passwd|secret|api_key|apikey)\s*[:=]\s*[\'"][^\'"]+[\'"]/i',
        ];
        foreach ($patterns as $p) {
            $text = preg_replace($p, '[REDACTED]', $text);
        }

        return $text;
    }

    private function withRetry(callable $callback): mixed
    {
        $attempts = 0;
        $maxAttempts = (int) config('ai_assistant.retries.max_attempts', 3);
        $baseDelayMs = (int) config('ai_assistant.retries.base_delay_ms', 500);

        while (true) {
            $attempts++;
            try {
                return $callback();
            } catch (Throwable $e) {
                $msg = strtolower($e->getMessage());
                $retryable = str_contains($msg, '429') || str_contains($msg, 'rate limit')
                    || str_contains($msg, '503') || str_contains($msg, '502');
                if (! $retryable || $attempts >= $maxAttempts) {
                    throw $e;
                }
                usleep((int) (($baseDelayMs * (2 ** ($attempts - 1))) * 1000));
            }
        }
    }
}
