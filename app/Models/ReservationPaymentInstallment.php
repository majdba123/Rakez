<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ReservationPaymentInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_reservation_id',
        'due_date',
        'amount',
        'description',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the reservation that this installment belongs to.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'sales_reservation_id');
    }

    /**
     * Scope for pending installments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue installments.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString());
    }

    /**
     * Scope for paid installments.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Check if this installment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this installment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date < now()->toDateString();
    }

    /**
     * Check if this installment is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Mark this installment as paid.
     */
    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);
    }

    /**
     * Mark this installment as overdue.
     */
    public function markAsOverdue(): void
    {
        $this->update(['status' => 'overdue']);
    }
}

