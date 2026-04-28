<?php

namespace App\Infrastructure\Ads\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdsSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

