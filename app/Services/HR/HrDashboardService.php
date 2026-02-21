<?php

namespace App\Services\HR;

use App\Constants\ReservationStatus;
use App\Models\User;
use App\Models\Team;
use App\Models\SalesReservation;
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
     * Calculate average monthly sales per employee (KPI: متوسط مبيع الموظف الشهري).
     * Formula per spec: عدد المشاريع المباعة ÷ عدد الموظفين.
     * "مشاريع مباعة" = confirmed reservations in the period; "عدد الموظفين" = all active employees.
     */
    public function calculateAvgSalesPerEmployee(int $year, int $month): float
    {
        $employeesCount = User::active()->count();

        if ($employeesCount === 0) {
            return 0.0;
        }

        $confirmedReservations = SalesReservation::where('status', ReservationStatus::CONFIRMED)
            ->whereYear('confirmed_at', $year)
            ->whereMonth('confirmed_at', $month)
            ->count();

        return round($confirmedReservations / $employeesCount, 2);
    }

    /**
     * Calculate average monthly team sales.
     * Formula: SUM(team_confirmed_reservations) / teams_count
     */
    public function calculateAvgTeamSales(int $year, int $month): float
    {
        $teamsCount = Team::count();

        if ($teamsCount === 0) {
            return 0.0;
        }

        $totalReservations = 0;
        $teams = Team::with('members')->get();

        foreach ($teams as $team) {
            $memberIds = $team->members->pluck('id');
            $teamReservations = SalesReservation::whereIn('marketing_employee_id', $memberIds)
                ->where('status', ReservationStatus::CONFIRMED)
                ->whereYear('confirmed_at', $year)
                ->whereMonth('confirmed_at', $month)
                ->count();
            $totalReservations += $teamReservations;
        }

        return round($totalReservations / $teamsCount, 2);
    }

    /**
     * Get active employees count.
     */
    public function getActiveEmployeesCount(): int
    {
        return User::active()->count();
    }

    /**
     * Calculate average target achievement rate (KPI: متوسط نسبة تحقيق الأهداف).
     * Formula: مجموع نسب تحقيق الأهداف لجميع الموظفين ÷ عددهم.
     * Note: Only employees with assigned targets (marketers / sales) are included; non-marketers have no targets in the system.
     */
    public function calculateAvgTargetAchievement(int $year, int $month): float
    {
        $marketers = User::active()->marketers()->get();

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
     * No-op for backwards compatibility (cache removed).
     */
    public function clearCache(): void
    {
    }
}

