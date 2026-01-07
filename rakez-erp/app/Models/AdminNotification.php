<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the user who created this notification.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the read records for this notification.
     */
    public function reads()
    {
        return $this->hasMany(AdminNotificationRead::class);
    }

    /**
     * Get admins who have read this notification.
     */
    public function readByUsers()
    {
        return $this->belongsToMany(User::class, 'admin_notification_reads')
                    ->withPivot('read_at');
    }

    /**
     * Check if a specific admin has read this notification.
     */
    public function isReadBy(User $user)
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    /**
     * Mark as read by a specific admin.
     */
    public function markAsReadBy(User $user)
    {
        if (!$this->isReadBy($user)) {
            $this->reads()->create([
                'user_id' => $user->id,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Create notification for new employee.
     */
    public static function createForNewEmployee(User $employee, ?User $createdBy = null)
    {
        return self::create([
            'title' => 'موظف جديد',
            'message' => 'تم إضافة موظف جديد: ' . $employee->name,
            'type' => 'employee_added',
            'data' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_email' => $employee->email,
                'employee_type' => $employee->type,
            ],
            'created_by' => $createdBy?->id,
        ]);
    }
}

