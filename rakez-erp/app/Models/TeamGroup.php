<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeamGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'description',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teamGroupLeader(): HasOne
    {
        return $this->hasOne(TeamGroupLeader::class);
    }
}
