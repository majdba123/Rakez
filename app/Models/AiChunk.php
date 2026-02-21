<?php

namespace App\Models;

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

    protected $casts = [
        'meta_json' => 'array',
        'embedding_json' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'document_id');
    }
}
