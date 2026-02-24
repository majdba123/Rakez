<?php

namespace App\Infrastructure\Ads\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdsOutcomeEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hashed_identifiers' => 'array',
            'click_ids' => 'array',
            'payload' => 'array',
            'platform_response' => 'array',
            'occurred_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }
}
