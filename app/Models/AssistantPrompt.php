<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantPrompt extends Model
{
    protected $table = 'assistant_prompts';

    protected $fillable = [
        'key',
        'content_md',
        'language',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter only active prompts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by language.
     */
    public function scopeForLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Get a prompt by key and language.
     */
    public static function getByKey(string $key, string $language = 'ar'): ?self
    {
        return static::query()
            ->active()
            ->where('key', $key)
            ->where('language', $language)
            ->first();
    }
}

