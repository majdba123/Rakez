<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesProjectAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'leader_id',
        'contract_id',
        'assigned_by',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the leader assigned to this project.
     */
    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Get the contract (project) for this assignment.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user who assigned this project.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope a query to filter by leader.
     */
    public function scopeByLeader($query, $leaderId)
    {
        return $query->where('leader_id', $leaderId);
    }

    /**
     * Scope a query to get active assignments on a specific date.
     * An assignment is active if:
     * - start_date is null or <= given date
     * - end_date is null or >= given date
     */
    public function scopeActiveOnDate($query, $date = null)
    {
        $date = $date ?? now()->toDateString();
        
        return $query->where(function ($q) use ($date) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', $date);
        });
    }

    /**
     * Scope a query to get currently active assignments.
     */
    public function scopeActive($query)
    {
        return $this->scopeActiveOnDate($query, now()->toDateString());
    }

    /**
     * Check if this assignment overlaps with another assignment for the same leader.
     */
    public function overlapsWith(SalesProjectAssignment $other): bool
    {
        // Must be for the same leader
        if ($this->leader_id !== $other->leader_id) {
            return false;
        }

        // Get date ranges
        $thisStart = $this->start_date ? $this->start_date->toDateString() : '1970-01-01';
        $thisEnd = $this->end_date ? $this->end_date->toDateString() : '9999-12-31';
        $otherStart = $other->start_date ? $other->start_date->toDateString() : '1970-01-01';
        $otherEnd = $other->end_date ? $other->end_date->toDateString() : '9999-12-31';

        // Check for overlap: two ranges overlap if start1 <= end2 && start2 <= end1
        return $thisStart <= $otherEnd && $otherStart <= $thisEnd;
    }

    /**
     * Check if this assignment is currently active.
     */
    public function isActive(): bool
    {
        $today = now()->toDateString();
        
        $startOk = $this->start_date === null || $this->start_date->toDateString() <= $today;
        $endOk = $this->end_date === null || $this->end_date->toDateString() >= $today;
        
        return $startOk && $endOk;
    }
}
