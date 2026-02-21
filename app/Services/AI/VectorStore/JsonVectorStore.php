<?php

namespace App\Services\AI\VectorStore;

use App\Models\AiChunk;
use Illuminate\Support\Facades\DB;

/**
 * Vector store using JSON column for embeddings (MySQL / PostgreSQL without pgvector).
 * Dev/small dataset only; for scale use Postgres+pgvector or external vector DB.
 */
class JsonVectorStore implements VectorStoreInterface
{
    public function __construct(
        private readonly \App\Services\AI\OpenAIEmbeddingClient $embeddingClient
    ) {}

    /**
     * @inheritdoc
     */
    public function search(string $query, array $filters, int $limit): array
    {
        $queryEmbedding = $this->embeddingClient->embed($query);
        if (empty($queryEmbedding)) {
            return [];
        }

        $chunks = AiChunk::query()
            ->whereNotNull('embedding_json')
            ->when(! empty($filters['document_id'] ?? null), fn ($q) => $q->where('document_id', $filters['document_id']))
            ->get();

        $scored = [];
        foreach ($chunks as $chunk) {
            $emb = $chunk->embedding_json;
            if (! is_array($emb)) {
                continue;
            }
            $score = $this->cosineSimilarity($queryEmbedding, $emb);
            $scored[] = [
                'document_id' => $chunk->document_id,
                'chunk_id' => $chunk->id,
                'content' => $chunk->content_text,
                'meta' => $chunk->meta_json ?? [],
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @inheritdoc
     */
    public function upsertChunks(array $chunks): void
    {
        foreach ($chunks as $item) {
            AiChunk::query()->where('id', $item['chunk_id'])->update([
                'embedding_json' => $item['embedding'] ?? null,
            ]);
        }
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        $normA = sqrt($normA);
        $normB = sqrt($normB);
        if ($normA < 1e-9 || $normB < 1e-9) {
            return 0.0;
        }

        return $dot / ($normA * $normB);
    }
}
