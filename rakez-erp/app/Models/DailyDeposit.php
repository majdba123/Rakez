<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyDeposit extends Model
{
    use HasFactory;

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
