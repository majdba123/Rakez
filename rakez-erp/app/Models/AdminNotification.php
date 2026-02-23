<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
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

    public function markAsRead()
    {
        $this->update(['status' => 'read']);
    }

    // Send notification to ALL admins
    public static function notifyAllAdmins($message)
    {
        $admins = User::where('type', 'admin')->get();

        foreach ($admins as $admin) {
            self::create([
                'user_id' => $admin->id,
                'message' => $message,
            ]);
        }
    }

    // Create for new employee
    public static function createForNewEmployee(User $employee)
    {
        self::notifyAllAdmins('New employee added with ID: ' . $employee->id);
    }
}
