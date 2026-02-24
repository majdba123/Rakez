<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_info',
        'source',
        'campaign_platform',
        'campaign_id',
        'campaign_type',
        'lead_score',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'status',
        'project_id',
        'assigned_to',
        'last_ai_call_id',
        'ai_call_count',
        'ai_qualification_status',
        'ai_call_notes',
    ];

    public function project()
    {
        return $this->belongsTo(Contract::class, 'project_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function aiCalls()
    {
        return $this->hasMany(AiCall::class, 'lead_id');
    }

    public function lastAiCall()
    {
        return $this->belongsTo(AiCall::class, 'last_ai_call_id');
    }
}
