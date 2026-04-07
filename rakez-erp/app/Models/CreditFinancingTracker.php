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
     * Stages 1–5: fixed calendar-day duration per stage (business workflow).
     */
    public const STAGE_DURATION_DAYS = [
        1 => 2, // Contact with client
        2 => 3, // Upload request
        3 => 3, // Output review
        4 => 2, // Visit reviewer
        5 => 5, // Operations
    ];

    public const STAGE_6_SUPPORTED_DAYS = 10;

    public const STAGE_6_UNSUPPORTED_DAYS = 5;

    /**
     * Calendar days for stage $stage when the tracker is in the given bank mode.
     * Stage 6 is the pre-transfer (الإفراغ) window; duration depends on supported vs not supported bank only.
     */
    public static function durationDaysForStage(int $stage, bool $isSupportedBank): int
    {
        if ($stage === 6) {
            return $isSupportedBank ? self::STAGE_6_SUPPORTED_DAYS : self::STAGE_6_UNSUPPORTED_DAYS;
        }

        return self::STAGE_DURATION_DAYS[$stage] ?? 0;
    }

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
        'stage_6_status',
        'stage_6_completed_at',
        'stage_6_deadline',
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
        'stage_6_completed_at' => 'datetime',
        'stage_6_deadline' => 'datetime',
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
            for ($i = 1; $i <= 6; $i++) {
                $q->orWhere("stage_{$i}_status", 'overdue');
            }
        });
    }

    /**
     * Get the current active stage number (1–6). When all stages are completed, returns 6.
     */
    public function getCurrentStage(): int
    {
        for ($i = 1; $i <= 6; $i++) {
            $status = $this->{"stage_{$i}_status"};
            if (in_array($status, ['pending', 'in_progress', 'overdue'], true)) {
                return $i;
            }
        }

        return 6;
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
     * Check if all six stages are completed.
     */
    public function allStagesCompleted(): bool
    {
        for ($i = 1; $i <= 6; $i++) {
            if ($this->{"stage_{$i}_status"} !== 'completed') {
                return false;
            }
        }

        return true;
    }

    /**
     * Total planned duration: 25 calendar days (supported bank) or 20 (not supported).
     */
    public function getTotalExpectedDays(): int
    {
        $days = 0;
        for ($s = 1; $s <= 6; $s++) {
            $days += self::durationDaysForStage($s, (bool) $this->is_supported_bank);
        }

        return $days;
    }

    /**
     * Get remaining days until overall planned end (from tracker creation).
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
        for ($i = 1; $i <= 6; $i++) {
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
