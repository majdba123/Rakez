<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'message',
        'voice_path',
        'voice_duration_seconds',
        'attachment_path',
        'attachment_original_name',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'voice_duration_seconds' => 'integer',
    ];

    protected $appends = [
        'voice_url',
        'attachment_url',
    ];

    protected $hidden = [
        'voice_path',
        'attachment_path',
    ];

    public function getVoiceUrlAttribute(): ?string
    {
        if (!$this->voice_path) {
            return null;
        }

        return Storage::disk('public')->url($this->voice_path);
    }

    public function isVoice(): bool
    {
        return $this->type === 'voice';
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function hasAttachment(): bool
    {
        return $this->isImage() || $this->isVideo() || $this->isFile();
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who sent this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}

