<?php

namespace App\Services\AI\Rag;

use App\Models\AiChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SplMinHeap;

class VectorSearchService
{
    /**
     * Search for the most similar chunks to the given query embedding.
     *
     * @param  array<float>  $queryEmbedding
     * @param  int|null  $ownerUserId  When set, only documents owned by this user (unless admin).
     * @return Collection<int, AiChunk> Collection with 'similarity' appended.
     */
    public function search(
        array $queryEmbedding,
        int $limit = 5,
        float $minSimilarity = 0.7,
        ?int $documentId = null,
        ?int $ownerUserId = null,
        bool $allowAllDocuments = false,
    ): Collection {
        if ($this->shouldUsePgVector()) {
            $vector = $this->searchPgVector($queryEmbedding, $limit, $minSimilarity, $documentId, $ownerUserId, $allowAllDocuments);
            if ($vector->isNotEmpty()) {
                return $vector;
            }
            return $this->searchKeywordFallback($limit, $documentId, $ownerUserId, $allowAllDocuments);
        }

        $vector = $this->searchPhpHeap($queryEmbedding, $limit, $minSimilarity, $documentId, $ownerUserId, $allowAllDocuments);
        if ($vector->isNotEmpty()) {
            return $vector;
        }

        return $this->searchKeywordFallback($limit, $documentId, $ownerUserId, $allowAllDocuments);
    }

    private function shouldUsePgVector(): bool
    {
        return config('database.default') === 'pgsql'
            && Schema::hasColumn('ai_chunks', 'embedding_vector');
    }

    /**
     * @return Collection<int, AiChunk>
     */
    private function searchPgVector(
        array $queryEmbedding,
        int $limit,
        float $minSimilarity,
        ?int $documentId,
        ?int $ownerUserId,
        bool $allowAllDocuments,
    ): Collection {
        $vecLiteral = '[' . implode(',', array_map('floatval', $queryEmbedding)) . ']';

        $bindings = [];
        $where = ['c.embedding_vector IS NOT NULL'];

        if (! $allowAllDocuments && $ownerUserId !== null) {
            $where[] = 'd.uploaded_by_user_id = ?';
            $bindings[] = $ownerUserId;
        }

        if ($documentId !== null) {
            $where[] = 'c.document_id = ?';
            $bindings[] = $documentId;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT c.id, c.document_id, c.chunk_index, c.content_text, c.meta_json, c.tokens, c.embedding_json,
                   1 - (c.embedding_vector <=> ?::vector) AS similarity
            FROM ai_chunks c
            INNER JOIN ai_documents d ON d.id = c.document_id
            WHERE {$whereSql}
            ORDER BY c.embedding_vector <=> ?::vector
            LIMIT ?
        ";

        array_unshift($bindings, $vecLiteral);
        $bindings[] = $vecLiteral;
        $bindings[] = $limit * 4;

        $rows = DB::select($sql, $bindings);

        $out = [];
        foreach ($rows as $row) {
            $sim = (float) $row->similarity;
            if ($sim < $minSimilarity) {
                continue;
            }
            $chunk = new AiChunk;
            $chunk->forceFill([
                'id' => $row->id,
                'document_id' => $row->document_id,
                'chunk_index' => $row->chunk_index,
                'content_text' => $row->content_text,
                'meta_json' => is_string($row->meta_json) ? json_decode($row->meta_json, true) : $row->meta_json,
                'tokens' => $row->tokens,
                'embedding_json' => is_string($row->embedding_json) ? json_decode($row->embedding_json, true) : $row->embedding_json,
            ]);
            $chunk->setAttribute('similarity', round($sim, 4));
            $chunk->makeHidden('embedding_json');
            $out[] = $chunk;
            if (count($out) >= $limit) {
                break;
            }
        }

        return new Collection($out);
    }

    /**
     * @return Collection<int, AiChunk>
     */
    private function searchPhpHeap(
        array $queryEmbedding,
        int $limit,
        float $minSimilarity,
        ?int $documentId,
        ?int $ownerUserId,
        bool $allowAllDocuments,
    ): Collection {
        $query = AiChunk::withEmbeddings()
            ->select('ai_chunks.id', 'ai_chunks.document_id', 'ai_chunks.chunk_index', 'ai_chunks.content_text', 'ai_chunks.meta_json', 'ai_chunks.tokens', 'ai_chunks.embedding_json')
            ->when(! $allowAllDocuments && $ownerUserId !== null, function ($q) use ($ownerUserId) {
                $q->join('ai_documents', 'ai_documents.id', '=', 'ai_chunks.document_id')
                    ->where('ai_documents.uploaded_by_user_id', $ownerUserId);
            })
            ->when($documentId !== null, fn ($q) => $q->where('ai_chunks.document_id', $documentId));

        $heap = new class extends SplMinHeap
        {
            protected function compare(mixed $value1, mixed $value2): int
            {
                return ($value1['similarity'] <=> $value2['similarity']);
            }
        };

        $query->chunkById(1000, function ($chunks) use ($queryEmbedding, $minSimilarity, $limit, $heap) {
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
        }, 'ai_chunks.id', 'id');

        $results = [];
        while (! $heap->isEmpty()) {
            $item = $heap->extract();
            $chunk = $item['chunk'];
            $chunk->setAttribute('similarity', round($item['similarity'], 4));
            $chunk->makeHidden('embedding_json');
            $results[] = $chunk;
        }

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

    /**
     * Fallback when vector similarity returns no matches.
     *
     * @return Collection<int, AiChunk>
     */
    private function searchKeywordFallback(
        int $limit,
        ?int $documentId,
        ?int $ownerUserId,
        bool $allowAllDocuments
    ): Collection {
        $queryText = (string) request()->input('query', '');
        $tokens = $this->extractQueryTokens($queryText);
        if ($tokens === []) {
            return collect();
        }

        $q = AiChunk::query()
            ->select('ai_chunks.*')
            ->when(! $allowAllDocuments && $ownerUserId !== null, function ($builder) use ($ownerUserId) {
                $builder->join('ai_documents', 'ai_documents.id', '=', 'ai_chunks.document_id')
                    ->where('ai_documents.uploaded_by_user_id', $ownerUserId);
            })
            ->when($documentId !== null, fn ($builder) => $builder->where('ai_chunks.document_id', $documentId));

        $q->where(function ($builder) use ($tokens) {
            foreach ($tokens as $token) {
                $builder->orWhere('ai_chunks.content_text', 'like', '%'.$token.'%');
            }
        });

        $rows = $q->limit($limit)->get();
        foreach ($rows as $row) {
            $row->setAttribute('similarity', 0.71);
            $row->makeHidden('embedding_json');
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function extractQueryTokens(string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $parts = array_values(array_unique(array_filter($parts, fn ($p) => mb_strlen((string) $p) >= 3)));

        return array_slice($parts, 0, 6);
    }
}
