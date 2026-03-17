<?php

namespace App\Services\AI\Tools;

use App\Models\User;
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

            // Search for similar chunks
            $results = $this->vectorSearchService->search(
                queryEmbedding: $queryEmbedding,
                limit: $limit,
                minSimilarity: $minSimilarity,
                documentId: $documentId,
            );

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
                $matches[] = [
                    'content' => $chunk->content_text,
                    'similarity' => $chunk->similarity,
                    'document_id' => $chunk->document_id,
                    'chunk_index' => $chunk->chunk_index,
                    'tokens' => $chunk->tokens,
                    'meta' => $chunk->meta_json,
                ];

                $document = $chunk->document;
                $sourceRefs[] = [
                    'type' => 'document',
                    'title' => $document->title ?? 'Document #' . $chunk->document_id,
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
}
