<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiCall extends Model
{
    protected $attributes = [
        'status' => 'pending',
        'direction' => 'outbound',
        'current_question_index' => 0,
        'total_questions_asked' => 0,
        'total_questions_answered' => 0,
        'attempt_number' => 1,
    ];

    protected $fillable = [
        'lead_id',
        'customer_type',
        'customer_name',
        'phone_number',
        'script_id',
        'twilio_call_sid',
        'status',
        'direction',
        'started_at',
        'ended_at',
        'duration_seconds',
        'total_questions_asked',
        'total_questions_answered',
        'call_summary',
        'sentiment_score',
        'current_question_index',
        'attempt_number',
        'initiated_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'total_questions_asked' => 'integer',
            'total_questions_answered' => 'integer',
            'sentiment_score' => 'decimal:2',
            'current_question_index' => 'integer',
            'attempt_number' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(AiCallScript::class, 'script_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiCallMessage::class)->orderBy('created_at');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('initiated_by', $userId);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'ringing', 'in_progress']);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'no_answer', 'busy', 'cancelled']);
    }

    public function markAsRinging(string $callSid): void
    {
        $this->update([
            'status' => 'ringing',
            'twilio_call_sid' => $callSid,
        ]);
    }

    public function markAsInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(?string $summary = null): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'duration_seconds' => $this->started_at
                ? now()->diffInSeconds($this->started_at)
                : null,
            'call_summary' => $summary,
        ]);
    }

    public function markAsFailed(string $reason = 'unknown'): void
    {
        $this->update([
            'status' => 'failed',
            'ended_at' => now(),
            'call_summary' => 'Call failed: ' . $reason,
        ]);
    }

    public function advanceQuestion(): void
    {
        $this->increment('current_question_index');
        $this->increment('total_questions_asked');
    }

    public function recordAnswer(): void
    {
        $this->increment('total_questions_answered');
    }
}
