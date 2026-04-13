<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafeWriteExecutionOutcome extends Model
{
    protected $fillable = [
        'user_id',
        'action_key',
        'idempotency_key',
        'proposal_fingerprint',
        'sales_reservation_action_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
