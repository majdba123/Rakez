<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;


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

    /**
     * Get sales reservations created by this user (as marketing employee).
     */
    public function salesReservations()
    {
        return $this->hasMany(\App\Models\SalesReservation::class, 'marketing_employee_id');
    }

    /**
     * Get sales targets assigned to this user (as marketer).
     */
    public function salesTargetsAsMarketer()
    {
        return $this->hasMany(\App\Models\SalesTarget::class, 'marketer_id');
    }

    /**
     * Get sales targets created by this user (as leader).
     */
    public function salesTargetsAsLeader()
    {
        return $this->hasMany(\App\Models\SalesTarget::class, 'leader_id');
    }

    /**
     * Get attendance schedules for this user.
     */
    public function attendanceSchedules()
    {
        return $this->hasMany(\App\Models\SalesAttendanceSchedule::class, 'user_id');
    }

    /**
     * Get marketing tasks assigned to this user.
     */
    public function marketingTasks()
    {
        return $this->hasMany(\App\Models\MarketingTask::class, 'marketer_id');
    }

    /**
     * Get sales project assignments for this user (as leader).
     */
    public function salesProjectAssignments()
    {
        return $this->hasMany(\App\Models\SalesProjectAssignment::class, 'leader_id');
    }

    /**
     * Get sales reservation actions performed by this user.
     */
    public function salesReservationActions()
    {
        return $this->hasMany(\App\Models\SalesReservationAction::class);
    }

    /**
     * Get the marketing project teams this user belongs to.
     */
    public function marketingProjectTeams()
    {
        return $this->hasMany(MarketingProjectTeam::class);
    }

    /**
     * Get the marketing projects this user leads.
     */
    public function ledMarketingProjects()
    {
        return $this->hasMany(MarketingProject::class, 'assigned_team_leader');
    }

    /**
     * Get the employee marketing plans for this user.
     */
    public function employeeMarketingPlans()
    {
        return $this->hasMany(EmployeeMarketingPlan::class);
    }

    /**
     * Get the leads assigned to this user.
     */
    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    /**
     * Check if user is a sales team leader.
     */
    public function isSalesLeader(): bool
    {
        return $this->hasRole('sales_leader') || ($this->type === 'sales' && $this->is_manager === true);
    }

    /**
     * Sync roles from the 'type' column.
     * Useful for migration or when 'type' is updated.
     */
    public function syncRolesFromType(): void
    {
        $roleName = $this->type;
        if ($this->type === 'sales' && $this->is_manager) {
            $roleName = 'sales_leader';
        }

        if (\Spatie\Permission\Models\Role::where('name', $roleName)->exists()) {
            $this->syncRoles([$roleName]);
        }
    }
}
