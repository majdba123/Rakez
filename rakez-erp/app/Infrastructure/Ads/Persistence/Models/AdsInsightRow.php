<?php

namespace App\Infrastructure\Ads\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdsInsightRow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_metrics' => 'array',
            'date_start' => 'date:Y-m-d',
            'date_stop' => 'date:Y-m-d',
        ];
    }
}
