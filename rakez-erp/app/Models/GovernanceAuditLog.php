<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'event',
        'subject_type',
        'subject_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
