<?php

namespace App\Infrastructure\Ads\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdsPlatformAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'meta' => 'array',
            'token_expires_at' => 'datetime',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
