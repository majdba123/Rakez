<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptVersion extends Model
{
    public $timestamps = false;

    protected $table = 'ai_prompt_versions';

    protected $fillable = [
        'prompt_key',
        'version',
        'content',
        'created_by',
        'is_active',
        'performance_score',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'performance_score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the active version for a prompt key.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get versions for a specific prompt key.
     */
    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('prompt_key', $key);
    }
}
