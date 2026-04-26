<?php

namespace App\Models;

use App\Enums\ExecutiveDirectorLineStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Standalone sales executive-director line (not tied to a sales target).
 */
class ExecutiveDirectorLine extends Model
{
    use HasFactory;

    protected $table = 'executive_director_lines';

    protected $fillable = [
        'line_type',
        'value',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'status' => ExecutiveDirectorLineStatus::class,
    ];

    /**
     * Teams this executive line is assigned to (sales managers can assign one or many).
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'executive_director_line_team')
            ->withTimestamps();
    }

    /**
     * Sub-groups (within assigned teams) that the sales leader links to this line for group leaders.
     */
    public function teamGroups(): BelongsToMany
    {
        return $this->belongsToMany(TeamGroup::class, 'executive_director_line_team_group')
            ->withTimestamps();
    }
}
