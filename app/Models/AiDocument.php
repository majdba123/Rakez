<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocument extends Model
{
    protected $table = 'ai_documents';

    protected $fillable = [
        'type',
        'title',
        'source_uri',
        'meta_json',
        'created_by',
        'content_hash',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiChunk::class, 'document_id');
    }
}
