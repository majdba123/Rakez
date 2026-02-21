<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeveloperMarketingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'average_cpm',
        'average_cpc',
        'marketing_value',
        'expected_impressions',
        'expected_clicks',
    ];

    protected $casts = [
        'average_cpm' => 'decimal:2',
        'average_cpc' => 'decimal:2',
        'marketing_value' => 'decimal:2',
        'expected_impressions' => 'integer',
        'expected_clicks' => 'integer',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
