<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Task;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    /**
     * Get the user who created this team.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get contracts assigned to this team.
     */
    public function contracts()
    {
        return $this->belongsToMany(Contract::class, 'contract_team')
            ->withTimestamps();
    }

    /**
     * Get members (users) belonging to this team.
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'team_id');
    }

    /**
     * Get active members of this team.
     */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(User::class, 'team_id')->where('is_active', true);
    }

    /**
     * Get marketers (sales type users) in this team.
     */
    public function marketers(): HasMany
    {
        return $this->hasMany(User::class, 'team_id')->where('type', 'sales');
    }

    /**
     * Get the average target achievement rate for this team.
     * Returns percentage (0-100).
     */
    public function getAverageTargetAchievement(?int $year = null, ?int $month = null): float
    {
        $marketers = $this->marketers()->get();

        if ($marketers->isEmpty()) {
            return 0.0;
        }

        $totalRate = 0.0;
        foreach ($marketers as $marketer) {
            $totalRate += $marketer->getTargetAchievementRate($year, $month);
        }

        return round($totalRate / $marketers->count(), 2);
    }

    /**
     * Get the total number of confirmed reservations for this team.
     */
    public function getConfirmedReservationsCount(?int $year = null, ?int $month = null): int
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        return SalesReservation::whereIn('marketing_employee_id', $this->members()->pluck('id'))
            ->where('status', \App\Constants\ReservationStatus::CONFIRMED)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();
    }

    /**
     * Get the number of projects assigned to this team.
     */
    public function getProjectsCount(): int
    {
        return $this->contracts()->count();
    }

    /**
     * Get project locations (cities) for this team.
     */
    public function getProjectLocations(): array
    {
        return $this->contracts()
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->toArray();
    }

    /**
     * Get tasks assigned to this team (department).
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'team_id');
    }
}


