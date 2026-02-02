<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_reservation_id',
        'contract_id',
        'contract_unit_id',
        'amount',
        'payment_method',
        'client_name',
        'payment_date',
        'commission_source',
        'status',
        'confirmed_by',
        'confirmed_at',
        'refunded_at',
        'notes',
        'claim_file_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'confirmed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the sales reservation that owns this deposit.
     */
    public function salesReservation()
    {
        return $this->belongsTo(SalesReservation::class);
    }

    /**
     * Get the contract (project) for this deposit.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the contract unit for this deposit.
     */
    public function contractUnit()
    {
        return $this->belongsTo(ContractUnit::class);
    }

    /**
     * Get the user who confirmed this deposit.
     */
    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Scope a query to only include pending deposits.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include received deposits.
     */
    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    /**
     * Scope a query to only include refunded deposits.
     */
    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Scope a query to only include confirmed deposits.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $from = null, $to = null)
    {
        if ($from) {
            $query->whereDate('payment_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('payment_date', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope a query to filter by commission source.
     */
    public function scopeByCommissionSource($query, string $source)
    {
        return $query->where('commission_source', $source);
    }

    /**
     * Check if deposit is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if deposit is received.
     */
    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    /**
     * Check if deposit is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Check if deposit is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Confirm receipt of the deposit.
     */
    public function confirmReceipt(int $confirmedBy): void
    {
        $this->status = 'confirmed';
        $this->confirmed_by = $confirmedBy;
        $this->confirmed_at = now();
        $this->save();
    }

    /**
     * Mark the deposit as received.
     */
    public function markAsReceived(): void
    {
        $this->status = 'received';
        $this->save();
    }

    /**
     * Refund the deposit.
     */
    public function refund(): void
    {
        $this->status = 'refunded';
        $this->refunded_at = now();
        $this->save();
    }

    /**
     * Check if deposit is refundable based on commission source.
     * If commission source is 'buyer', deposit is non-refundable.
     */
    public function isRefundable(): bool
    {
        return $this->commission_source === 'owner' && !$this->isRefunded();
    }

    /**
     * Get total received deposits for a project.
     */
    public static function totalReceivedForProject(int $contractId): float
    {
        return self::where('contract_id', $contractId)
            ->whereIn('status', ['received', 'confirmed'])
            ->sum('amount');
    }

    /**
     * Get total refunded deposits for a project.
     */
    public static function totalRefundedForProject(int $contractId): float
    {
        return self::where('contract_id', $contractId)
            ->where('status', 'refunded')
            ->sum('amount');
    }
}
