<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInteractionLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_interaction_logs';

    protected $fillable = [
        'user_id',
        'session_id',
        'correlation_id',
        'section',
        'request_type',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'tool_calls_count',
        'had_error',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'had_error' => 'boolean',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'latency_ms' => 'float',
            'tool_calls_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
