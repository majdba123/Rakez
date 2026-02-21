<?php

namespace App\Services\AI\VectorStore;

/**
 * No-op vector store when vector_driver=disabled (e.g. SQLite tools-only mode).
 */
class DisabledVectorStore implements VectorStoreInterface
{
    /**
     * @inheritdoc
     */
    public function search(string $query, array $filters, int $limit): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function upsertChunks(array $chunks): void
    {
        // No-op
    }
}
