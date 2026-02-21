<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyDeposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'deposit_id',
        'date',
        'amount',
        'booking_id',
        'project_id',
    ];

    /**
     * Optional link to the source Deposit (when populated from accounting confirmation).
     */
    public function deposit()
    {
        return $this->belongsTo(Deposit::class);
    }

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Contract::class, 'project_id');
    }
}
