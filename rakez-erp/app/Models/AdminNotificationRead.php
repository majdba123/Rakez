<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotificationRead extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'admin_notification_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Get the notification.
     */
    public function notification()
    {
        return $this->belongsTo(AdminNotification::class, 'admin_notification_id');
    }

    /**
     * Get the user who read the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

