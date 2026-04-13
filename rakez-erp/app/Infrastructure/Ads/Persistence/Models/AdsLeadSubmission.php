<?php

namespace App\Infrastructure\Ads\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdsLeadSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_time' => 'datetime',
            'synced_at' => 'datetime',
            'extra_data' => 'array',
            'raw_payload' => 'array',
        ];
    }
}

