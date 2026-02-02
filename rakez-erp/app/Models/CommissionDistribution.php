<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_id',
        'user_id',
        'type',
        'external_name',
        'bank_account',
        'percentage',
        'amount',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the commission that owns this distribution.
     */
    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    /**
     * Get the user who receives this distribution.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved this distribution.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Calculate amount based on commission net amount and percentage.
     */
    public function calculateAmount(): void
    {
        if ($this->commission) {
            $this->amount = ($this->commission->net_amount * $this->percentage) / 100;
        }
    }

    /**
     * Scope a query to only include pending distributions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved distributions.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include rejected distributions.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope a query to only include paid distributions.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope a query to filter by distribution type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if distribution is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if distribution is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if distribution is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Approve the distribution.
     */
    public function approve(int $approvedBy): void
    {
        $this->status = 'approved';
        $this->approved_by = $approvedBy;
        $this->approved_at = now();
        $this->save();
    }

    /**
     * Reject the distribution.
     */
    public function reject(int $approvedBy, ?string $notes = null): void
    {
        $this->status = 'rejected';
        $this->approved_by = $approvedBy;
        $this->approved_at = now();
        if ($notes) {
            $this->notes = $notes;
        }
        $this->save();
    }

    /**
     * Mark the distribution as paid.
     */
    public function markAsPaid(): void
    {
        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();
    }

    /**
     * Check if this is for an external marketer.
     */
    public function isExternal(): bool
    {
        return $this->type === 'external_marketer' || $this->type === 'other';
    }

    /**
     * Get display name (user name or external name).
     */
    public function getDisplayName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->external_name ?? 'Unknown';
    }
}
