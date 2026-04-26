<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamGroupLeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_group_id',
        'user_id',
    ];

    public function teamGroup(): BelongsTo
    {
        return $this->belongsTo(TeamGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
