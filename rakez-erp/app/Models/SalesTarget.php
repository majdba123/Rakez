<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'leader_id',
        'marketer_id',
        'contract_id',
        'contract_unit_id',
        'must_sell_units_count',
        'assigned_target_value',
        'target_type',
        'start_date',
        'end_date',
        'status',
        'leader_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'must_sell_units_count' => 'integer',
        'assigned_target_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the leader who assigned this target.
     */
    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Get the marketer assigned to this target.
     */
    public function marketer()
    {
        return $this->belongsTo(User::class, 'marketer_id');
    }

    /**
     * Get the contract (project) for this target.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the primary contract unit for this target (backward compatibility; first of assigned units).
     */
    public function contractUnit()
    {
        return $this->belongsTo(ContractUnit::class);
    }

    /**
     * Legacy pivot: historical rows may still list units; new targets do not sync inventory here.
     */
    public function contractUnits()
    {
        return $this->belongsToMany(
            ContractUnit::class,
            'sales_target_units',
            'sales_target_id',
            'contract_unit_id'
        )->withTimestamps();
    }

    /**
     * Executive director lines (type, value, status) for this target.
     */
    public function executiveDirectors()
    {
        return $this->hasMany(SalesTargetExecutiveDirector::class);
    }

    /**
     * Scope a query to filter by marketer.
     */
    public function scopeByMarketer($query, $marketerId)
    {
        return $query->where('marketer_id', $marketerId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $from = null, $to = null)
    {
        if ($from) {
            $query->whereDate('start_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('end_date', '<=', $to);
        }
        return $query;
    }
}
