<?php

namespace App\Services\AI\Tools;

use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Services\AI\Rag\EmbeddingService;
use App\Services\AI\Rag\SemanticCache;
use App\Services\AI\Rag\VectorSearchService;
use Throwable;

class RagSearchTool implements ToolContract
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
        private readonly VectorSearchService $vectorSearchService,
        private readonly SemanticCache $cache,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('use-ai-assistant')) {
            return ToolResponse::denied('use-ai-assistant');
        }

        $query = $args['query'] ?? '';
        if ($query === '') {
            return ToolResponse::error('Query is required for RAG search.');
        }

        $limit = $args['limit'] ?? (int) config('ai_assistant.rag.search_limit', 5);
        $minSimilarity = (float) config('ai_assistant.rag.min_similarity', 0.7);
        $documentId = $args['filters']['document_id'] ?? null;

        // Check semantic cache
        $cacheKey = $query . ':' . ($documentId ?? 'all') . ':' . $limit;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // Generate query embedding
            $queryEmbedding = $this->embeddingService->embed($query);

            $allowAll = $user->hasRole('admin');

            // Search for similar chunks (scoped to user documents; admin can search all)
            $results = $this->vectorSearchService->search(
                queryEmbedding: $queryEmbedding,
                limit: $limit,
                minSimilarity: $minSimilarity,
                documentId: $documentId,
                ownerUserId: $allowAll ? null : $user->id,
                allowAllDocuments: $allowAll,
            );

            $ids = $results->pluck('document_id')->unique()->filter()->all();
            $titles = $ids === []
                ? new Collection
                : AiDocument::query()->whereIn('id', $ids)->pluck('title', 'id');

            if ($results->isEmpty()) {
                $response = ToolResponse::success('tool_rag_search', ['query' => $query], [
                    'matches' => [],
                    'total_found' => 0,
                    'message' => 'لم يتم العثور على نتائج مطابقة.',
                ]);

                $this->cache->put($cacheKey, $response);

                return $response;
            }

            $matches = [];
            $sourceRefs = [];

            foreach ($results as $chunk) {
                $snippet = $this->snippet((string) $chunk->content_text);
                $title = $titles[$chunk->document_id] ?? 'Document #' . $chunk->document_id;

                $matches[] = [
                    'document_id' => $chunk->document_id,
                    'title' => $title,
                    'snippet' => $snippet,
                    'score' => $chunk->similarity,
                    'chunk_index' => $chunk->chunk_index,
                    'source_ref' => "doc:{$chunk->document_id}:chunk:{$chunk->chunk_index}",
                ];

                $sourceRefs[] = [
                    'type' => 'document',
                    'title' => $title,
                    'ref' => "doc:{$chunk->document_id}:chunk:{$chunk->chunk_index}",
                ];
            }

            $response = ToolResponse::success('tool_rag_search', ['query' => $query], [
                'matches' => $matches,
                'total_found' => count($matches),
            ], $sourceRefs);

            $this->cache->put($cacheKey, $response);

            return $response;
        } catch (Throwable $e) {
            return ToolResponse::error('RAG search failed: ' . $e->getMessage());
        }
    }

    private function snippet(string $text, int $max = 280): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (mb_strlen($t) <= $max) {
            return $t;
        }

        return mb_substr($t, 0, $max) . '…';
    }
}
