<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMarketingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_project_id',
        'user_id',
        'commission_value',
        'marketing_value',
        'marketing_percent',
        'direct_contact_percent',
        'platform_distribution',
        'campaign_distribution',
        'campaign_distribution_by_platform',
    ];

    protected $casts = [
        'commission_value' => 'decimal:2',
        'marketing_value' => 'decimal:2',
        'marketing_percent' => 'decimal:2',
        'direct_contact_percent' => 'decimal:2',
        'platform_distribution' => 'json',
        'campaign_distribution' => 'json',
        'campaign_distribution_by_platform' => 'json',
    ];

    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns()
    {
        return $this->hasMany(MarketingCampaign::class);
    }

    public function budgetDistribution()
    {
        return $this->hasOne(MarketingBudgetDistribution::class);
    }
}
