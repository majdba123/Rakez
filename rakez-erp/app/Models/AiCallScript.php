<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiCallScript extends Model
{
    protected $fillable = [
        'name',
        'target_type',
        'language',
        'questions',
        'greeting_text',
        'closing_text',
        'max_retries_per_question',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'is_active' => 'boolean',
            'max_retries_per_question' => 'integer',
        ];
    }

    public function calls(): HasMany
    {
        return $this->hasMany(AiCall::class, 'script_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTarget($query, string $targetType)
    {
        return $query->whereIn('target_type', [$targetType, 'both']);
    }

    public function getQuestionCount(): int
    {
        return count($this->questions ?? []);
    }

    public function getQuestionAt(int $index): ?array
    {
        return $this->questions[$index] ?? null;
    }
}
