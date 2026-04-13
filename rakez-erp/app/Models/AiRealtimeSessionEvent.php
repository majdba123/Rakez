<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRealtimeSessionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'user_id',
        'sequence',
        'direction',
        'event_type',
        'transport_event_type',
        'transport_event_id',
        'error_code',
        'state_before',
        'state_after',
        'correlation_id',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiRealtimeSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
