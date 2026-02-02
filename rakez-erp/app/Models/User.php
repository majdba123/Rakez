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
        'team_id', // Foreign key to teams table (replaces deprecated 'team' string field)
        'cv_path',
        'contract_path',
        'identity_number',
        'birthday',
        'date_of_works',
        'contract_type',
        'iban',
        'salary',
        'marital_status',
        'nationality',
        'job_title',
        'department',
        'additional_benefits',
        'probation_period_days',
        'work_type',
        'signature_path',
        'work_phone_approval',
        'logo_usage_approval',
        'is_active',
        'contract_end_date',
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
        'contract_end_date' => 'date',
        'salary' => 'decimal:2',
        'is_manager' => 'boolean',
        'work_phone_approval' => 'boolean',
        'logo_usage_approval' => 'boolean',
        'is_active' => 'boolean',
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
     * Check if user is a project management manager.
     */
    public function isProjectManagementManager(): bool
    {
        return $this->type === 'project_management' && $this->is_manager === true;
    }

    /**
     * Get effective permissions including dynamic manager permissions.
     */
    public function getEffectivePermissions(): array
    {
        $permissions = $this->getAllPermissions()->pluck('name')->toArray();
        
        // Add manager-specific permissions dynamically
        if ($this->isProjectManagementManager()) {
            $managerPermissions = [
                'projects.approve',
                'projects.media.approve',
                'projects.archive',
                'exclusive_projects.approve',
            ];
            $permissions = array_merge($permissions, $managerPermissions);
        }
        
        return array_unique($permissions);
    }

    /**
     * Check if user has a specific permission (including dynamic permissions).
     */
    public function hasEffectivePermission(string $permission): bool
    {
        return in_array($permission, $this->getEffectivePermissions(), true);
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

    /**
     * Get exclusive project requests created by this user.
     */
    public function exclusiveProjectRequests()
    {
        return $this->hasMany(\App\Models\ExclusiveProjectRequest::class, 'requested_by');
    }

    /**
     * Get exclusive project requests approved by this user.
     */
    public function approvedExclusiveProjects()
    {
        return $this->hasMany(\App\Models\ExclusiveProjectRequest::class, 'approved_by');
    }

    /**
     * Get waiting list entries created by this user.
     */
    public function waitingListEntries()
    {
        return $this->hasMany(\App\Models\SalesWaitingList::class, 'sales_staff_id');
    }

    /**
     * Get warnings issued to this employee.
     */
    public function warnings()
    {
        return $this->hasMany(EmployeeWarning::class, 'user_id');
    }

    /**
     * Get warnings issued by this user (as HR/manager).
     */
    public function issuedWarnings()
    {
        return $this->hasMany(EmployeeWarning::class, 'issued_by');
    }

    /**
     * Get employee contracts.
     */
    public function employeeContracts()
    {
        return $this->hasMany(EmployeeContract::class, 'user_id');
    }

    /**
     * Get active employee contract.
     */
    public function activeContract()
    {
        return $this->hasOne(EmployeeContract::class, 'user_id')
            ->where('status', 'active')
            ->latest();
    }

    /**
     * Get the target achievement rate for this marketer.
     * Returns percentage (0-100).
     */
    public function getTargetAchievementRate(?int $year = null, ?int $month = null): float
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $targets = $this->salesTargetsAsMarketer()
            ->whereYear('start_date', '<=', $year)
            ->whereYear('end_date', '>=', $year)
            ->get();

        if ($targets->isEmpty()) {
            return 0.0;
        }

        $totalTargets = $targets->count();
        $completedTargets = $targets->where('status', 'completed')->count();

        return $totalTargets > 0 ? round(($completedTargets / $totalTargets) * 100, 2) : 0.0;
    }

    /**
     * Get the number of deposits (down payments) from this marketer's reservations.
     */
    public function getDepositsCount(?int $year = null, ?int $month = null): int
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        return $this->salesReservations()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereIn('status', ['under_negotiation', 'confirmed'])
            ->count();
    }

    /**
     * Get warnings count for this employee.
     */
    public function getWarningsCount(?int $year = null): int
    {
        $query = $this->warnings();
        
        if ($year) {
            $query->whereYear('warning_date', $year);
        }

        return $query->count();
    }

    /**
     * Check if employee is in probation period.
     */
    public function isInProbation(): bool
    {
        if (!$this->date_of_works || !$this->probation_period_days) {
            return false;
        }

        $probationEnd = $this->date_of_works->copy()->addDays($this->probation_period_days);
        return now()->lt($probationEnd);
    }

    /**
     * Get probation end date.
     */
    public function getProbationEndDate(): ?\Carbon\Carbon
    {
        if (!$this->date_of_works || !$this->probation_period_days) {
            return null;
        }

        return $this->date_of_works->copy()->addDays($this->probation_period_days);
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sales/marketing users.
     */
    public function scopeMarketers($query)
    {
        return $query->where('type', 'sales');
    }

    /**
     * Get commission distributions for this user.
     */
    public function commissionDistributions()
    {
        return $this->hasMany(\App\Models\CommissionDistribution::class);
    }

    /**
     * Get commission distributions approved by this user.
     */
    public function approvedCommissionDistributions()
    {
        return $this->hasMany(\App\Models\CommissionDistribution::class, 'approved_by');
    }

    /**
     * Get deposits confirmed by this user.
     */
    public function confirmedDeposits()
    {
        return $this->hasMany(\App\Models\Deposit::class, 'confirmed_by');
    }
}
