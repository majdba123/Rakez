<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyDeposit extends Model
{
    use HasFactory;

    /**
     * `booking_id` has no FK in migrations; treat as opaque until linked to a concrete table.
     *
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'amount',
        'booking_id',
        'project_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Contract::class, 'project_id');
    }
}
