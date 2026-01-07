<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
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

    // Get the admin user
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

    // Send notification to ALL admins when employee is added
    public static function notifyAllAdmins($message, $title = null, $data = null)
    {
        $admins = User::where('type', 'admin')->get();

        foreach ($admins as $admin) {
            self::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);
        }
    }

    // Create for new employee - notify all admins (simple message with ID)
    public static function createForNewEmployee(User $employee)
    {
        self::notifyAllAdmins(
            message: 'New employee added with ID: ' . $employee->id
        );
    }
}
