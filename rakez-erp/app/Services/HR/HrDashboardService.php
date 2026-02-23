<?php

namespace App\Services\HR;

use App\Models\User;
use App\Models\Team;
use App\Models\SalesReservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HrDashboardService
{
    /**
     * Get all dashboard KPIs.
     */
    public function getDashboardKpis(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        return [
            'avg_monthly_sales_per_employee' => $this->calculateAvgSalesPerEmployee($year, $month),
            'avg_monthly_team_sales' => $this->calculateAvgTeamSales($year, $month),
            'active_employees_count' => $this->getActiveEmployeesCount(),
            'avg_target_achievement_rate' => $this->calculateAvgTargetAchievement($year, $month),
        ];
    }

    /**
     * Calculate average monthly sales per employee.
     * Formula: confirmed_reservations / active_sales_employees
     */
    public function calculateAvgSalesPerEmployee(int $year, int $month): float
    {
        $cacheKey = "hr_avg_sales_per_employee_{$year}_{$month}";

        return Cache::remember($cacheKey, 60, function () use ($year, $month) {
            $salesEmployeesCount = User::active()
                ->where('type', 'sales')
                ->count();

            if ($salesEmployeesCount === 0) {
                return 0.0;
            }

            $confirmedReservations = SalesReservation::where('status', 'confirmed')
                ->whereYear('confirmed_at', $year)
                ->whereMonth('confirmed_at', $month)
                ->count();

            return round($confirmedReservations / $salesEmployeesCount, 2);
        });
    }

    /**
     * Calculate average monthly team sales.
     * Formula: SUM(team_confirmed_reservations) / teams_count
     */
    public function calculateAvgTeamSales(int $year, int $month): float
    {
        $cacheKey = "hr_avg_team_sales_{$year}_{$month}";

        return Cache::remember($cacheKey, 60, function () use ($year, $month) {
            $teamsCount = Team::count();

            if ($teamsCount === 0) {
                return 0.0;
            }

            $totalReservations = 0;
            $teams = Team::with('members')->get();

            foreach ($teams as $team) {
                $memberIds = $team->members->pluck('id');
                $teamReservations = SalesReservation::whereIn('marketing_employee_id', $memberIds)
                    ->where('status', 'confirmed')
                    ->whereYear('confirmed_at', $year)
                    ->whereMonth('confirmed_at', $month)
                    ->count();
                $totalReservations += $teamReservations;
            }

            return round($totalReservations / $teamsCount, 2);
        });
    }

    /**
     * Get active employees count.
     */
    public function getActiveEmployeesCount(): int
    {
        return Cache::remember('hr_active_employees_count', 60, function () {
            return User::active()->count();
        });
    }

    /**
     * Calculate average target achievement rate.
     * Formula: SUM(achievement_rates) / marketers_count
     */
    public function calculateAvgTargetAchievement(int $year, int $month): float
    {
        $cacheKey = "hr_avg_target_achievement_{$year}_{$month}";

        return Cache::remember($cacheKey, 60, function () use ($year, $month) {
            $marketers = User::active()->marketers()->get();

            if ($marketers->isEmpty()) {
                return 0.0;
            }

            $totalRate = 0.0;
            foreach ($marketers as $marketer) {
                $totalRate += $marketer->getTargetAchievementRate($year, $month);
            }

            return round($totalRate / $marketers->count(), 2);
        });
    }

    /**
     * Get employees by department breakdown.
     */
    public function getEmployeesByDepartment(): array
    {
        return User::active()
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    /**
     * Clear dashboard cache.
     */
    public function clearCache(): void
    {
        $year = now()->year;
        $month = now()->month;

        Cache::forget("hr_avg_sales_per_employee_{$year}_{$month}");
        Cache::forget("hr_avg_team_sales_{$year}_{$month}");
        Cache::forget('hr_active_employees_count');
        Cache::forget("hr_avg_target_achievement_{$year}_{$month}");
    }
}

