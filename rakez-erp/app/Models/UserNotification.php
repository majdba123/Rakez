<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'message',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    public function scopePublic($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopePrivate($query)
    {
        return $query->whereNotNull('user_id');
    }

    public function markAsRead()
    {
        $this->update(['status' => 'read']);
    }

    public function isPublic()
    {
        return $this->user_id === null;
    }
}
