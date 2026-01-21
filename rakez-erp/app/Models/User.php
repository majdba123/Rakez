<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


    protected $fillable = [
        'name',
        'type',
        'is_manager',
        'phone',
        'email',
        'password',
        'team_id',
        'cv_path',
        'contract_path',
        'identity_number',
        'birthday',
        'date_of_works',
        'contract_type',
        'iban',
        'salary',
        'marital_status',
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'identity_date' => 'date',
        'birthday' => 'date',
        'date_of_works' => 'date',
        'salary' => 'decimal:2',
        'is_manager' => 'boolean',
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }


    // User notifications (private + can access public)
    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    // Admin notifications (only for admin users)
    public function adminNotifications()
    {
        return $this->hasMany(AdminNotification::class);
    }

    // Pending user notifications count
    public function pendingUserNotificationsCount()
    {
        return $this->userNotifications()->pending()->count();
    }

    // Pending admin notifications count
    public function pendingAdminNotificationsCount()
    {
        return $this->adminNotifications()->pending()->count();
    }

    public function isAdmin()
    {
        return $this->type === 'admin';
    }

    /**
     * Check if user is a manager
     * هل المستخدم مدير
     */
    public function isManager(): bool
    {
        return $this->is_manager === true;
    }
}
