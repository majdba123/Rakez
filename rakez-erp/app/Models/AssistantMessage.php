<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    protected $table = 'assistant_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'capability_used',
        'tokens',
        'latency_ms',
    ];

    protected $casts = [
        'tokens' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    /**
     * Scope for user messages.
     */
    public function scopeUserMessages(Builder $query): Builder
    {
        return $query->where('role', 'user');
    }

    /**
     * Scope for assistant messages.
     */
    public function scopeAssistantMessages(Builder $query): Builder
    {
        return $query->where('role', 'assistant');
    }
}

