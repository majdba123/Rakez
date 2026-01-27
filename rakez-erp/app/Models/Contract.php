<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ContractInfo;
use App\Models\SecondPartyData;
use App\Models\BoardsDepartment;
use App\Models\PhotographyDepartment;
use App\Models\MontageDepartment;
use App\Models\Team;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'project_name',
        'developer_name',
        'developer_number',
        'city',
        'district',
        'units',
        'project_image_url',
        'developer_requiment',
        'status',
        'notes',
        'emergency_contact_number',
        'security_guard_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'units' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the contract.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contract info (one-to-one).
     */
    public function info()
    {
        return $this->hasOne(ContractInfo::class);
    }

    /**
     * Get the second party data (one-to-one).
     * بيانات الطرف الثاني
     */
    public function secondPartyData()
    {
        return $this->hasOne(SecondPartyData::class);
    }

    /**
     * Get the boards department data (one-to-one).
     * قسم اللوحات
     */
    public function boardsDepartment()
    {
        return $this->hasOne(BoardsDepartment::class);
    }

    /**
     * Get the photography department data (one-to-one).
     * قسم التصوير
     */
    public function photographyDepartment()
    {
        return $this->hasOne(PhotographyDepartment::class);
    }

    /**
     * Get the montage department data (one-to-one).
     * قسم المونتاج
     */
    public function montageDepartment()
    {
        return $this->hasOne(MontageDepartment::class);
    }

    /**
     * Teams assigned to this contract (many-to-many)
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'contract_team')
            ->withTimestamps();
    }

    /**
     * Scope to filter contracts by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter contracts by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if contract is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if contract is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if user owns this contract.
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Normalize units array (ensure all required fields and proper types)
     */
    public function normalizeUnits(): void
    {
        if (!$this->units || !is_array($this->units)) {
            $this->units = [];
            return;
        }

        $normalized = [];
        foreach ($this->units as $unit) {
            if (isset($unit['type'], $unit['count'], $unit['price'])) {
                $normalized[] = [
                    'type' => trim((string) $unit['type']),
                    'count' => (int) $unit['count'],
                    'price' => (float) $unit['price'],
                ];
            }
        }
        $this->units = $normalized;
    }

    /**
     * Process units array (normalize only)
     */
    public function calculateUnitTotals(): void
    {
        $this->normalizeUnits();
    }

    /**
     * Scope: Get pending contracts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get approved contracts
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get contracts in specific city
     */
    public function scopeInCity($query, $city)
    {
        return $query->where('city', $city);
    }

    /**
     * Scope: Get contracts by developer
     */
    public function scopeByDeveloper($query, $developerName)
    {
        return $query->where('developer_name', 'like', '%' . $developerName . '%');
    }

    /**
     * Scope: Get contracts with minimum value
     */
    public function scopeMinimumValue($query, $amount)
    {
        return $query->where('total_units_value', '>=', $amount);
    }

    /**
     * Get the sales reservations for this contract.
     */
    public function salesReservations()
    {
        return $this->hasMany(\App\Models\SalesReservation::class);
    }

    /**
     * Get the sales targets for this contract.
     */
    public function salesTargets()
    {
        return $this->hasMany(\App\Models\SalesTarget::class);
    }

    /**
     * Get the attendance schedules for this contract.
     */
    public function attendanceSchedules()
    {
        return $this->hasMany(\App\Models\SalesAttendanceSchedule::class);
    }

    /**
     * Get the marketing tasks for this contract.
     */
    public function marketingTasks()
    {
        return $this->hasMany(\App\Models\MarketingTask::class);
    }

    /**
     * Get the marketing project for this contract.
     */
    public function marketingProject()
    {
        return $this->hasOne(MarketingProject::class);
    }

    /**
     * Get the developer marketing plan for this contract.
     */
    public function developerMarketingPlan()
    {
        return $this->hasOne(DeveloperMarketingPlan::class);
    }

    /**
     * Get the project media for this contract.
     */
    public function projectMedia()
    {
        return $this->hasMany(ProjectMedia::class);
    }

    /**
     * Get the daily deposits for this contract.
     */
    public function dailyDeposits()
    {
        return $this->hasMany(DailyDeposit::class, 'project_id');
    }

    /**
     * Get the leads for this contract.
     */
    public function leads()
    {
        return $this->hasMany(Lead::class, 'project_id');
    }

    /**
     * Get the sales project assignments for this contract.
     */
    public function salesProjectAssignments()
    {
        return $this->hasMany(\App\Models\SalesProjectAssignment::class);
    }
}
