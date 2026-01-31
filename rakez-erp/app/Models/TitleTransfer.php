<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TitleTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_reservation_id',
        'processed_by',
        'status',
        'scheduled_date',
        'completed_date',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the reservation for this transfer.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'sales_reservation_id');
    }

    /**
     * Get the user processing this transfer.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope for pending transfers.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for transfers in preparation.
     */
    public function scopeInPreparation(Builder $query): Builder
    {
        return $query->where('status', 'preparation');
    }

    /**
     * Scope for scheduled transfers.
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope for completed transfers.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if transfer is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transfer is in preparation.
     */
    public function isInPreparation(): bool
    {
        return $this->status === 'preparation';
    }

    /**
     * Check if transfer is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if transfer is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get days until scheduled date.
     */
    public function getDaysUntilScheduled(): ?int
    {
        if (!$this->scheduled_date) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->scheduled_date, false));
    }
}



