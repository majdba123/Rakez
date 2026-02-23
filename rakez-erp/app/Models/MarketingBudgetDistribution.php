<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingBudgetDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_project_id',
        'plan_type',
        'employee_marketing_plan_id',
        'developer_marketing_plan_id',
        'total_budget',
        'platform_distribution',
        'platform_objectives',
        'platform_costs',
        'cost_source',
        'conversion_rate',
        'average_booking_value',
        'calculated_results',
    ];

    protected $casts = [
        'total_budget' => 'decimal:2',
        'platform_distribution' => 'array',
        'platform_objectives' => 'array',
        'platform_costs' => 'array',
        'cost_source' => 'array',
        'conversion_rate' => 'decimal:2',
        'average_booking_value' => 'decimal:2',
        'calculated_results' => 'array',
    ];

    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }

    public function employeeMarketingPlan()
    {
        return $this->belongsTo(EmployeeMarketingPlan::class);
    }

    public function developerMarketingPlan()
    {
        return $this->belongsTo(DeveloperMarketingPlan::class);
    }
}
