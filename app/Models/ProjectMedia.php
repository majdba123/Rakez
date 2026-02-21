<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMedia extends Model
{
    use HasFactory;

    protected $table = 'project_media';

    protected $fillable = [
        'contract_id',
        'type',
        'url',
        'department',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * Scope: only media approved (after editing) for marketing display.
     */
    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
