<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class NegotiationApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_reservation_id',
        'requested_by',
        'approved_by',
        'status',
        'negotiation_reason',
        'original_price',
        'proposed_price',
        'manager_notes',
        'deadline_at',
        'responded_at',
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'proposed_price' => 'decimal:2',
        'deadline_at' => 'datetime',
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the reservation that this approval belongs to.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'sales_reservation_id');
    }

    /**
     * Get the user who requested this approval.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the manager who approved/rejected this request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for pending approvals.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue approvals.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('deadline_at', '<', now());
    }

    /**
     * Check if this approval is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this approval is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->deadline_at < now();
    }

    /**
     * Check if this approval can be responded to.
     */
    public function canRespond(): bool
    {
        return $this->isPending() && !$this->isOverdue();
    }

    /**
     * Get the discount amount.
     */
    public function getDiscountAmount(): float
    {
        return (float) $this->original_price - (float) $this->proposed_price;
    }

    /**
     * Get the discount percentage.
     */
    public function getDiscountPercentage(): float
    {
        if ((float) $this->original_price <= 0) {
            return 0;
        }
        return round(($this->getDiscountAmount() / (float) $this->original_price) * 100, 2);
    }
}

