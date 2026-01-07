<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'status',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    // Get the user (null if public)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope: pending notifications
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Scope: read notifications
    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    // Scope: public notifications (user_id is NULL)
    public function scopePublic($query)
    {
        return $query->whereNull('user_id');
    }

    // Scope: private notifications (user_id is NOT NULL)
    public function scopePrivate($query)
    {
        return $query->whereNotNull('user_id');
    }

    // Scope: for specific user (private + public)
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereNull('user_id'); // Include public
        });
    }

    // Mark as read
    public function markAsRead()
    {
        $this->update(['status' => 'read']);
    }

    // Check if read
    public function isRead()
    {
        return $this->status === 'read';
    }

    // Check if public
    public function isPublic()
    {
        return $this->user_id === null;
    }

    // Create public notification (for everyone)
    public static function createPublic($message, $title = null, $data = null)
    {
        return self::create([
            'user_id' => null, // NULL = public
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    // Create private notification (for specific user)
    public static function createForUser($userId, $message, $title = null, $data = null)
    {
        return self::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
