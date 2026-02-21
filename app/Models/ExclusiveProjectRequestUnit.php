<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExclusiveProjectRequestUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'exclusive_project_request_id',
        'unit_type',
        'count',
        'average_price',
    ];

    protected $casts = [
        'count' => 'integer',
        'average_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the exclusive project request that owns this unit row.
     */
    public function exclusiveProjectRequest()
    {
        return $this->belongsTo(ExclusiveProjectRequest::class);
    }
}
