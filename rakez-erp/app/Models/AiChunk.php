<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChunk extends Model
{
    protected $table = 'ai_chunks';

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content_text',
        'meta_json',
        'tokens',
        'content_hash',
        'embedding_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'embedding_json' => 'array',
            'tokens' => 'integer',
            'chunk_index' => 'integer',
        ];
    }

    /**
     * The document this chunk belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'document_id');
    }

    /**
     * Scope: only chunks that have embeddings.
     */
    public function scopeWithEmbeddings(Builder $query): Builder
    {
        return $query->whereNotNull('embedding_json');
    }

    /**
     * Scope: chunks for a specific document.
     */
    public function scopeForDocument(Builder $query, int $documentId): Builder
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Check if this chunk has an embedding vector.
     */
    public function hasEmbedding(): bool
    {
        return ! empty($this->embedding_json);
    }
}
