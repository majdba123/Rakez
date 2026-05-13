<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRewardRecipient extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'project_reward_id',
        'user_id',
        'recipient_type',
        'sales_reservation_id',
        'sales_reservation_participant_id',
        'source_scope',
        'source_type',
        'percentage',
        'amount',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'percentage' => 'decimal:4',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function reward(): BelongsTo
    {
        return $this->belongsTo(ProjectReward::class, 'project_reward_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesReservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(SalesReservationParticipant::class, 'sales_reservation_participant_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
