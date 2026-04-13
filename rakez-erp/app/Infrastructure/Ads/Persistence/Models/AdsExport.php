<?php

namespace App\Infrastructure\Ads\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdsExport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

