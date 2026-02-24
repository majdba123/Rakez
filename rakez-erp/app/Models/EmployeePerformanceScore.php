<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePerformanceScore extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'composite_score' => 'decimal:2',
            'factor_scores' => 'array',
            'strengths' => 'array',
            'weaknesses' => 'array',
            'project_type_affinity' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
