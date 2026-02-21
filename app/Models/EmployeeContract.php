<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmployeeContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contract_data',
        'pdf_path',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'contract_data' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee for this contract.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for active contracts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for expiring contracts within X days.
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    /**
     * Scope for expired contracts (active with end_date in the past).
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString());
    }

    /**
     * Scope for ended contracts: expired, terminated, or active with end_date in the past.
     */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', ['expired', 'terminated'])
                ->orWhere(function (Builder $q2) {
                    $q2->where('status', 'active')
                        ->whereNotNull('end_date')
                        ->where('end_date', '<', now()->toDateString());
                });
        });
    }

    /**
     * Check if contract is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if contract is expiring within X days.
     */
    public function isExpiringWithin(int $days): bool
    {
        if (!$this->end_date || $this->status !== 'active') {
            return false;
        }
        
        return $this->end_date <= now()->addDays($days)->toDateString() 
            && $this->end_date >= now()->toDateString();
    }

    /**
     * Check if contract has expired.
     */
    public function hasExpired(): bool
    {
        if (!$this->end_date) {
            return false;
        }
        
        return $this->end_date < now()->toDateString();
    }

    /**
     * Get remaining days until expiration.
     */
    public function getRemainingDays(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        $diff = now()->diffInDays($this->end_date, false);
        return max(0, (int) $diff);
    }
}

