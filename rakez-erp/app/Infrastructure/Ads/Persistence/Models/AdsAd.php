<?php

namespace App\Infrastructure\Ads\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdsAd extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw_data' => 'array'];
    }
}
