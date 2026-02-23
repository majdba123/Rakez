<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpectedBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_project_id',
        'direct_communications',
        'hand_raises',
        'expected_bookings_count',
        'expected_booking_value',
        'conversion_rate',
    ];

    protected $casts = [
        'expected_booking_value' => 'decimal:2',
        'conversion_rate' => 'decimal:2',
    ];

    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }
}
