<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SalesWaitingList extends Model
{
    use HasFactory;

    protected $table = 'sales_waiting_list';

    protected $fillable = [
        'contract_id',
        'contract_unit_id',
        'sales_staff_id',
        'client_name',
        'client_mobile',
        'client_email',
        'priority',
        'status',
        'notes',
        'converted_to_reservation_id',
        'converted_at',
        'converted_by',
        'expires_at',
    ];

    protected $casts = [
        'priority' => 'integer',
        'converted_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the contract that this waiting list entry belongs to.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the contract unit that this waiting list entry is for.
     */
    public function contractUnit()
    {
        return $this->belongsTo(ContractUnit::class);
    }

    /**
     * Get the sales staff who created this waiting list entry.
     */
    public function salesStaff()
    {
        return $this->belongsTo(User::class, 'sales_staff_id');
    }

    /**
     * Get the reservation that this waiting list entry was converted to.
     */
    public function convertedToReservation()
    {
        return $this->belongsTo(SalesReservation::class, 'converted_to_reservation_id');
    }

    /**
     * Get the user who converted this waiting list entry.
     */
    public function convertedBy()
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    /**
     * Scope: Get active waiting list entries (status = waiting and not expired).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'waiting')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: Get expired waiting list entries.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'waiting')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope: Filter by unit.
     */
    public function scopeByUnit($query, int $unitId)
    {
        return $query->where('contract_unit_id', $unitId);
    }

    /**
     * Scope: Filter by sales staff.
     */
    public function scopeByStaff($query, int $staffId)
    {
        return $query->where('sales_staff_id', $staffId);
    }

    /**
     * Scope: Order by priority (higher priority first).
     */
    public function scopeOrderByPriority($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('created_at', 'asc');
    }

    /**
     * Check if this waiting list entry is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'waiting' 
            && $this->expires_at 
            && $this->expires_at->isPast();
    }

    /**
     * Check if this waiting list entry is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'waiting' 
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Mark this waiting list entry as converted.
     */
    public function markAsConverted(SalesReservation $reservation, User $convertedBy): void
    {
        $this->update([
            'status' => 'converted',
            'converted_to_reservation_id' => $reservation->id,
            'converted_at' => now(),
            'converted_by' => $convertedBy->id,
        ]);
    }

    /**
     * Mark this waiting list entry as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Mark this waiting list entry as expired.
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}
