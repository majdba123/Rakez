<?php

namespace App\Models;

use App\Enums\SalesTargetExecutiveDirectorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per–sales-target line for an executive director (type, value, status).
 */
class SalesTargetExecutiveDirector extends Model
{
    use HasFactory;

    protected $table = 'sales_target_executive_directors';

    protected $fillable = [
        'sales_target_id',
        'line_type',
        'value',
        'status',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'status' => SalesTargetExecutiveDirectorStatus::class,
    ];

    public function salesTarget(): BelongsTo
    {
        return $this->belongsTo(SalesTarget::class);
    }
}
