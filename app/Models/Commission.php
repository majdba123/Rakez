<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_unit_id',
        'sales_reservation_id',
        'final_selling_price',
        'commission_percentage',
        'total_amount',
        'vat',
        'marketing_expenses',
        'bank_fees',
        'net_amount',
        'commission_source',
        'team_responsible',
        'status',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'final_selling_price' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'vat' => 'decimal:2',
        'marketing_expenses' => 'decimal:2',
        'bank_fees' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the contract unit that owns this commission.
     */
    public function contractUnit()
    {
        return $this->belongsTo(ContractUnit::class);
    }

    /**
     * Get the sales reservation that owns this commission.
     */
    public function salesReservation()
    {
        return $this->belongsTo(SalesReservation::class);
    }

    /**
     * Get the commission distributions for this commission.
     */
    public function distributions()
    {
        return $this->hasMany(CommissionDistribution::class);
    }

    /**
     * Calculate net amount after deductions.
     */
    public function calculateNetAmount(): void
    {
        $this->net_amount = $this->total_amount - $this->vat - $this->marketing_expenses - $this->bank_fees;
    }

    /**
     * Calculate total commission based on final selling price and percentage.
     */
    public function calculateTotalAmount(): void
    {
        $this->total_amount = ($this->final_selling_price * $this->commission_percentage) / 100;
    }

    /**
     * Calculate VAT (15% of total amount).
     */
    public function calculateVAT(): void
    {
        $this->vat = ($this->total_amount * 15) / 100;
    }

    /**
     * Scope a query to only include pending commissions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved commissions.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include paid commissions.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Check if commission is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if commission is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if commission is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Approve the commission.
     */
    public function approve(): void
    {
        $this->status = 'approved';
        $this->approved_at = now();
        $this->save();
    }

    /**
     * Mark the commission as paid.
     */
    public function markAsPaid(): void
    {
        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();
    }
}
