<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingProjectTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_project_id',
        'user_id',
        'role',
    ];

    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
