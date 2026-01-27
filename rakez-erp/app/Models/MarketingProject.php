<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'status',
        'assigned_team_leader',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'assigned_team_leader');
    }

    public function teams()
    {
        return $this->hasMany(MarketingProjectTeam::class);
    }

    public function developerPlan()
    {
        return $this->hasOne(DeveloperMarketingPlan::class, 'contract_id', 'contract_id');
    }

    public function employeePlans()
    {
        return $this->hasMany(EmployeeMarketingPlan::class);
    }

    public function tasks()
    {
        return $this->hasMany(MarketingTask::class);
    }

    public function expectedBooking()
    {
        return $this->hasOne(ExpectedBooking::class);
    }
}
