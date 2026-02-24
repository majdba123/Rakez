<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCallMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ai_call_id',
        'role',
        'content',
        'question_key',
        'confidence',
        'timestamp_in_call',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'timestamp_in_call' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(AiCall::class, 'ai_call_id');
    }

    public function scopeFromAi($query)
    {
        return $query->where('role', 'ai');
    }

    public function scopeFromClient($query)
    {
        return $query->where('role', 'client');
    }
}
