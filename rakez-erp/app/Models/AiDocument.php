<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocument extends Model
{
    protected $table = 'ai_documents';

    protected $fillable = [
        'uploaded_by_user_id',
        'title',
        'source',
        'mime_type',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'uploaded_by_user_id' => 'integer',
        ];
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Get all chunks belonging to this document.
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(AiChunk::class, 'document_id')->orderBy('chunk_index');
    }

    /**
     * Get total token count across all chunks.
     */
    public function totalTokens(): int
    {
        return (int) $this->chunks()->sum('tokens');
    }

    /**
     * Get total chunk count.
     */
    public function chunkCount(): int
    {
        return (int) $this->chunks()->count();
    }
}
