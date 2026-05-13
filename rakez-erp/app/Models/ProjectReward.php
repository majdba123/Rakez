<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectReward extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'sales_reservation_id',
        'contract_id',
        'project_reward_setting_id',
        'calculation_mode',
        'calculation_base_amount',
        'reward_percentage',
        'source',
        'base_amount',
        'tax_enabled',
        'vat_percentage',
        'vat_amount',
        'total_amount',
        'distribution_pool_amount',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'calculation_base_amount' => 'decimal:2',
        'reward_percentage' => 'decimal:4',
        'base_amount' => 'decimal:2',
        'tax_enabled' => 'boolean',
        'vat_percentage' => 'decimal:4',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'distribution_pool_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function salesReservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ProjectRewardSetting::class, 'project_reward_setting_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ProjectRewardRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
