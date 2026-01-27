<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_marketing_plan_id',
        'platform',
        'campaign_type',
        'budget',
        'status',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    public function employeePlan()
    {
        return $this->belongsTo(EmployeeMarketingPlan::class, 'employee_marketing_plan_id');
    }
}
