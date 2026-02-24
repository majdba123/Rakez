<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSalesAttribution extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'marketing_spend' => 'decimal:4',
            'revenue' => 'decimal:4',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'reservation_id');
    }
}
