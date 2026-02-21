<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CreditFinancingTracker extends Model
{
    use HasFactory;

    /**
     * Stage deadline durations in hours.
     */
    public const STAGE_DEADLINES = [
        1 => 48,     // 48 hours
        2 => 120,    // 5 days
        3 => 72,     // 3 days
        4 => 48,     // 2 days
        5 => 120,    // 5 days
    ];

    /**
     * Additional days for supported banks.
     */
    public const SUPPORTED_BANK_EXTRA_DAYS = 5;

    protected $fillable = [
        'sales_reservation_id',
        'assigned_to',
        'stage_1_status',
        'bank_name',
        'client_salary',
        'employment_type',
        'stage_1_completed_at',
        'stage_1_deadline',
        'stage_2_status',
        'stage_2_completed_at',
        'stage_2_deadline',
        'stage_3_status',
        'stage_3_completed_at',
        'stage_3_deadline',
        'stage_4_status',
        'appraiser_name',
        'stage_4_completed_at',
        'stage_4_deadline',
        'stage_5_status',
        'stage_5_completed_at',
        'stage_5_deadline',
        'is_supported_bank',
        'overall_status',
        'rejection_reason',
        'completed_at',
    ];

    protected $casts = [
        'client_salary' => 'decimal:2',
        'is_supported_bank' => 'boolean',
        'stage_1_completed_at' => 'datetime',
        'stage_1_deadline' => 'datetime',
        'stage_2_completed_at' => 'datetime',
        'stage_2_deadline' => 'datetime',
        'stage_3_completed_at' => 'datetime',
        'stage_3_deadline' => 'datetime',
        'stage_4_completed_at' => 'datetime',
        'stage_4_deadline' => 'datetime',
        'stage_5_completed_at' => 'datetime',
        'stage_5_deadline' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the reservation for this tracker.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'sales_reservation_id');
    }

    /**
     * Get the user assigned to this tracker.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope for in-progress trackers.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('overall_status', 'in_progress');
    }

    /**
     * Scope for completed trackers.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('overall_status', 'completed');
    }

    /**
     * Scope for rejected trackers.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('overall_status', 'rejected');
    }

    /**
     * Scope for trackers with any overdue stage.
     */
    public function scopeWithOverdueStages(Builder $query): Builder
    {
        return $query->where(function ($q) {
            for ($i = 1; $i <= 5; $i++) {
                $q->orWhere("stage_{$i}_status", 'overdue');
            }
        });
    }

    /**
     * Get the current active stage number (1-5).
     */
    public function getCurrentStage(): int
    {
        for ($i = 1; $i <= 5; $i++) {
            $status = $this->{"stage_{$i}_status"};
            if (in_array($status, ['pending', 'in_progress', 'overdue'])) {
                return $i;
            }
        }
        return 5; // All completed
    }

    /**
     * Get the status of a specific stage.
     */
    public function getStageStatus(int $stage): string
    {
        return $this->{"stage_{$stage}_status"} ?? 'pending';
    }

    /**
     * Get the deadline for a specific stage.
     */
    public function getStageDeadline(int $stage): ?Carbon
    {
        return $this->{"stage_{$stage}_deadline"};
    }

    /**
     * Check if a stage is overdue.
     */
    public function isStageOverdue(int $stage): bool
    {
        $deadline = $this->getStageDeadline($stage);
        $status = $this->getStageStatus($stage);

        if (!$deadline || $status === 'completed') {
            return false;
        }

        return now()->gt($deadline);
    }

    /**
     * Check if all stages are completed.
     */
    public function allStagesCompleted(): bool
    {
        for ($i = 1; $i <= 5; $i++) {
            if ($this->{"stage_{$i}_status"} !== 'completed') {
                return false;
            }
        }
        return true;
    }

    /**
     * Get total expected days based on bank type.
     */
    public function getTotalExpectedDays(): int
    {
        $baseDays = 20; // Sum of all stage deadlines in days
        return $this->is_supported_bank 
            ? $baseDays + self::SUPPORTED_BANK_EXTRA_DAYS 
            : $baseDays;
    }

    /**
     * Get remaining days until overall deadline.
     */
    public function getRemainingDays(): ?int
    {
        if ($this->overall_status !== 'in_progress') {
            return null;
        }

        $startDate = $this->created_at;
        $endDate = $startDate->copy()->addDays($this->getTotalExpectedDays());
        
        return max(0, (int) now()->diffInDays($endDate, false));
    }

    /**
     * Get progress summary for all stages.
     */
    public function getProgressSummary(): array
    {
        $summary = [];
        for ($i = 1; $i <= 5; $i++) {
            $summary["stage_{$i}"] = [
                'status' => $this->{"stage_{$i}_status"},
                'deadline' => $this->{"stage_{$i}_deadline"},
                'completed_at' => $this->{"stage_{$i}_completed_at"},
                'is_overdue' => $this->isStageOverdue($i),
            ];
        }
        return $summary;
    }
}



