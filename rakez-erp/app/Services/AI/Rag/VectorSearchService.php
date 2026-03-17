<?php

namespace App\Services\AI\Rag;

use App\Models\AiChunk;
use Illuminate\Support\Collection;
use SplMinHeap;

class VectorSearchService
{
    /**
     * Search for the most similar chunks to the given query embedding.
     *
     * @param  array<float>  $queryEmbedding
     * @param  int  $limit  Max results to return.
     * @param  float  $minSimilarity  Minimum cosine similarity threshold.
     * @param  int|null  $documentId  Optional filter by document.
     * @return Collection<int, AiChunk> Collection with 'similarity' appended.
     */
    public function search(
        array $queryEmbedding,
        int $limit = 5,
        float $minSimilarity = 0.7,
        ?int $documentId = null,
    ): Collection {
        $query = AiChunk::withEmbeddings();

        if ($documentId !== null) {
            $query->forDocument($documentId);
        }

        // Use a min-heap to efficiently keep top-N results
        $heap = new class extends SplMinHeap
        {
            protected function compare(mixed $value1, mixed $value2): int
            {
                return ($value1['similarity'] <=> $value2['similarity']);
            }
        };

        // Process in chunks of 1000 to manage memory
        $query->select(['id', 'document_id', 'chunk_index', 'content_text', 'meta_json', 'tokens', 'embedding_json'])
            ->chunkById(1000, function ($chunks) use ($queryEmbedding, $minSimilarity, $limit, $heap) {
                foreach ($chunks as $chunk) {
                    $embedding = $chunk->embedding_json;

                    if (! is_array($embedding) || empty($embedding)) {
                        continue;
                    }

                    $similarity = self::cosineSimilarity($queryEmbedding, $embedding);

                    if ($similarity < $minSimilarity) {
                        continue;
                    }

                    if ($heap->count() < $limit) {
                        $heap->insert([
                            'chunk' => $chunk,
                            'similarity' => $similarity,
                        ]);
                    } elseif ($similarity > $heap->top()['similarity']) {
                        $heap->extract();
                        $heap->insert([
                            'chunk' => $chunk,
                            'similarity' => $similarity,
                        ]);
                    }
                }
            });

        // Extract results from heap and sort by similarity descending
        $results = [];
        while (! $heap->isEmpty()) {
            $item = $heap->extract();
            $chunk = $item['chunk'];
            $chunk->setAttribute('similarity', round($item['similarity'], 4));
            // Remove heavy embedding data from result
            $chunk->makeHidden('embedding_json');
            $results[] = $chunk;
        }

        // Reverse because we extracted from min-heap (lowest first)
        $results = array_reverse($results);

        return new Collection($results);
    }

    /**
     * Compute cosine similarity between two vectors.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     * @return float Similarity between -1 and 1.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        if ($length === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
