<?php

namespace App\Services\AI\Rag;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class EmbeddingService
{
    private string $model;

    private int $dimensions;

    private int $batchSize;

    public function __construct()
    {
        $this->model = config('ai_assistant.embeddings.model', 'text-embedding-3-small');
        $this->dimensions = (int) config('ai_assistant.embeddings.dimensions', 1536);
        $this->batchSize = (int) config('ai_assistant.embeddings.batch_size', 100);
    }

    /**
     * Generate an embedding vector for a single text.
     *
     * @return array<float> Vector of floats with $dimensions length.
     */
    public function embed(string $text): array
    {
        $text = $this->sanitize($text);

        if ($text === '') {
            return array_fill(0, $this->dimensions, 0.0);
        }

        $response = $this->withRetry(fn () => OpenAI::embeddings()->create([
            'model' => $this->model,
            'input' => $text,
            'dimensions' => $this->dimensions,
        ]));

        return $response->embeddings[0]->embedding;
    }

    /**
     * Generate embeddings for a batch of texts.
     *
     * @param  array<string>  $texts
     * @return array<int, array<float>> Indexed array of vectors.
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $texts = array_map(fn (string $t) => $this->sanitize($t), $texts);
        $results = [];
        $batches = array_chunk($texts, $this->batchSize, true);

        foreach ($batches as $batch) {
            $batchTexts = array_values($batch);
            $batchKeys = array_keys($batch);

            // Filter out empty texts
            $nonEmpty = [];
            $emptyKeys = [];
            foreach ($batchTexts as $i => $t) {
                if ($t === '') {
                    $emptyKeys[] = $batchKeys[$i];
                } else {
                    $nonEmpty[$batchKeys[$i]] = $t;
                }
            }

            // Fill empty with zero vectors
            foreach ($emptyKeys as $key) {
                $results[$key] = array_fill(0, $this->dimensions, 0.0);
            }

            if (! empty($nonEmpty)) {
                $response = $this->withRetry(fn () => OpenAI::embeddings()->create([
                    'model' => $this->model,
                    'input' => array_values($nonEmpty),
                    'dimensions' => $this->dimensions,
                ]));

                $keys = array_keys($nonEmpty);
                foreach ($response->embeddings as $i => $embedding) {
                    $results[$keys[$i]] = $embedding->embedding;
                }
            }
        }

        // Sort by original index
        ksort($results);

        return array_values($results);
    }

    /**
     * Get the configured embedding dimensions.
     */
    public function dimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Sanitize text for embedding: trim, collapse whitespace, truncate.
     */
    private function sanitize(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        // OpenAI text-embedding-3-small accepts up to ~8191 tokens (~32K chars)
        if (mb_strlen($text) > 30000) {
            $text = mb_substr($text, 0, 30000);
        }

        return $text;
    }

    /**
     * Retry logic matching OpenAIResponsesClient pattern.
     */
    private function withRetry(callable $callback): mixed
    {
        $maxAttempts = (int) config('ai_assistant.retries.max_attempts', 3);
        $baseDelayMs = (int) config('ai_assistant.retries.base_delay_ms', 500);
        $maxDelayMs = (int) config('ai_assistant.retries.max_delay_ms', 5000);
        $jitterMs = (int) config('ai_assistant.retries.jitter_ms', 250);

        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return $callback();
            } catch (Throwable $e) {
                $msg = strtolower($e->getMessage());

                $retryable =
                    str_contains($msg, '429') ||
                    str_contains($msg, 'rate limit') ||
                    str_contains($msg, 'timeout') ||
                    str_contains($msg, 'temporarily unavailable') ||
                    str_contains($msg, '503') ||
                    str_contains($msg, '502');

                if (! $retryable || $attempts >= $maxAttempts) {
                    Log::warning('EmbeddingService: request failed (no more retries)', [
                        'attempts' => $attempts,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                $delay = $baseDelayMs * (2 ** ($attempts - 1));
                $delay = min($delay, $maxDelayMs);
                $jitter = random_int(0, max(0, $jitterMs));
                usleep((int) (($delay + $jitter) * 1000));
            }
        }
    }
}
