<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReservationParticipant extends Model
{
    protected $fillable = [
        'sales_reservation_id',
        'user_id',
        'did_bring',
        'did_convince',
        'did_close',
        'weight',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'did_bring' => 'boolean',
        'did_convince' => 'boolean',
        'did_close' => 'boolean',
        'weight' => 'decimal:2',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'sales_reservation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
