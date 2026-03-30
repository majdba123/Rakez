<?php

namespace App\Services\AI\Rag;

use App\Services\AI\AiOpenAiGateway;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmbeddingService
{
    private string $model;

    private int $dimensions;

    private int $batchSize;

    public function __construct(
        private readonly AiOpenAiGateway $gateway,
    ) {
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

        try {
            $response = $this->gateway->embeddingsCreate([
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $this->dimensions,
            ], []);

            return $response->embeddings[0]->embedding;
        } catch (Throwable $e) {
            throw $e instanceof AiAssistantException ? $e : $this->gateway->normalizeException($e);
        }
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

            $nonEmpty = [];
            $emptyKeys = [];
            foreach ($batchTexts as $i => $t) {
                if ($t === '') {
                    $emptyKeys[] = $batchKeys[$i];
                } else {
                    $nonEmpty[$batchKeys[$i]] = $t;
                }
            }

            foreach ($emptyKeys as $key) {
                $results[$key] = array_fill(0, $this->dimensions, 0.0);
            }

            if (! empty($nonEmpty)) {
                try {
                    $response = $this->gateway->embeddingsCreate([
                        'model' => $this->model,
                        'input' => array_values($nonEmpty),
                        'dimensions' => $this->dimensions,
                    ], []);

                    $keys = array_keys($nonEmpty);
                    foreach ($response->embeddings as $i => $embedding) {
                        $results[$keys[$i]] = $embedding->embedding;
                    }
                } catch (Throwable $e) {
                    Log::warning('EmbeddingService: batch failed', ['error' => $e->getMessage()]);
                    throw $e instanceof AiAssistantException ? $e : $this->gateway->normalizeException($e);
                }
            }
        }

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

        if (mb_strlen($text) > 30000) {
            $text = mb_substr($text, 0, 30000);
        }

        return $text;
    }
}
