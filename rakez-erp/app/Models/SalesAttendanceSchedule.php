<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesAttendanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'user_id',
        'schedule_date',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the contract (project) for this schedule.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user assigned to this schedule.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created this schedule.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $from = null, $to = null)
    {
        if ($from) {
            $query->whereDate('schedule_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('schedule_date', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope a query to filter by contract.
     */
    public function scopeByContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }
}
