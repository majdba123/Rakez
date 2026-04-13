<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRealtimeSession extends Model
{
    public const STATUS_SESSION_CREATED = 'session_created';
    public const STATUS_SESSION_ACTIVE = 'session_active';
    public const STATUS_LISTENING = 'listening';
    public const STATUS_PARTIAL_TRANSCRIPT = 'partial_transcript';
    public const STATUS_ASSISTANT_THINKING = 'assistant_thinking';
    public const STATUS_TOOL_RUNNING = 'tool_running';
    public const STATUS_ASSISTANT_SPEAKING = 'assistant_speaking';
    public const STATUS_INTERRUPTED = 'interrupted';
    public const STATUS_RECONNECTING = 'reconnecting';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'public_id',
        'user_id',
        'status',
        'transport',
        'transport_mode',
        'transport_status',
        'section',
        'provider_model',
        'provider_session_id',
        'bridge_owner_token',
        'bridge_owner_pid',
        'bridge_started_at',
        'bridge_heartbeat_at',
        'rollback_target',
        'correlation_id',
        'duration_limit_seconds',
        'max_reconnects',
        'reconnect_count',
        'turn_number',
        'estimated_input_tokens',
        'estimated_output_tokens',
        'estimated_total_tokens',
        'metadata',
        'started_at',
        'last_activity_at',
        'expires_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
            'bridge_started_at' => 'datetime',
            'bridge_heartbeat_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AiRealtimeSessionEvent::class, 'session_id');
    }

    public static function terminalStates(): array
    {
        return [self::STATUS_ENDED];
    }
}
