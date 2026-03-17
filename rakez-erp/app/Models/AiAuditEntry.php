<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAuditEntry extends Model
{
    public $timestamps = false;

    protected $table = 'ai_audit_trail';

    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'input_summary',
        'output_summary',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
