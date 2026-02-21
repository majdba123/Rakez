<?php

namespace App\Services\AI\VectorStore;

interface VectorStoreInterface
{
    /**
     * Search for similar chunks by query embedding or text.
     *
     * @return array<int, array{document_id: int, chunk_id: int, content: string, meta: array, score: float}>
     */
    public function search(string $query, array $filters, int $limit): array;

    /**
     * Upsert chunks (with embeddings) into the store.
     *
     * @param  array<int, array{chunk_id: int, document_id: int, content: string, meta: array, embedding: array<float>}>  $chunks
     */
    public function upsertChunks(array $chunks): void;
}
