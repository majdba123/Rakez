<?php

namespace App\Services\HR;

use App\Constants\ReservationStatus;
use App\Models\User;
use App\Models\Team;
use App\Models\EmployeeContract;
use App\Models\SalesReservation;
use Illuminate\Support\Collection;

class HrReportService
{
    /**
     * Generate monthly team performance report.
     */
    public function getTeamPerformanceReport(int $year, int $month): array
    {
        $teams = Team::with(['members'])->get();

        $report = [];
        foreach ($teams as $team) {
            $memberIds = $team->members->pluck('id');

            $confirmedReservations = SalesReservation::whereIn('marketing_employee_id', $memberIds)
                ->where('status', ReservationStatus::CONFIRMED)
                ->whereYear('confirmed_at', $year)
                ->whereMonth('confirmed_at', $month)
                ->count();

            $avgTargetAchievement = $team->getAverageTargetAchievement($year, $month);

            $report[] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'members_count' => $team->members->count(),
                'active_members_count' => $team->members->where('is_active', true)->count(),
                'confirmed_reservations' => $confirmedReservations,
                'avg_target_achievement' => $avgTargetAchievement,
                'projects_count' => $team->contracts()->count(),
                'locations' => $team->getProjectLocations(),
            ];
        }

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
            ],
            'teams' => $report,
            'totals' => [
                'teams_count' => count($report),
                'total_members' => array_sum(array_column($report, 'members_count')),
                'total_reservations' => array_sum(array_column($report, 'confirmed_reservations')),
                'avg_target_achievement' => count($report) > 0
                    ? round(array_sum(array_column($report, 'avg_target_achievement')) / count($report), 2)
                    : 0,
            ],
        ];
    }

    /**
     * Generate marketer performance report.
     */
    public function getMarketerPerformanceReport(int $year, int $month, ?int $teamId = null): array
    {
        $query = User::active()->marketers()->with(['team', 'warnings']);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $marketers = $query->get();

        $report = [];
        foreach ($marketers as $marketer) {
            $report[] = [
                'user_id' => $marketer->id,
                'name' => $marketer->name,
                'email' => $marketer->email,
                'team_name' => $marketer->team?->name,
                'target_achievement_rate' => $marketer->getTargetAchievementRate($year, $month),
                'deposits_count' => $marketer->getDepositsCount($year, $month),
                'warnings_count' => $marketer->getWarningsCount($year),
                'is_in_probation' => $marketer->isInProbation(),
            ];
        }

        // Sort by achievement rate descending
        usort($report, fn($a, $b) => $b['target_achievement_rate'] <=> $a['target_achievement_rate']);

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
            ],
            'team_id' => $teamId,
            'marketers' => $report,
            'totals' => [
                'marketers_count' => count($report),
                'avg_achievement' => count($report) > 0
                    ? round(array_sum(array_column($report, 'target_achievement_rate')) / count($report), 2)
                    : 0,
                'total_deposits' => array_sum(array_column($report, 'deposits_count')),
                'total_warnings' => array_sum(array_column($report, 'warnings_count')),
            ],
        ];
    }

    /**
     * Generate employee count report.
     */
    public function getEmployeeCountReport(): array
    {
        $activeUsers = User::active()->get();
        $inactiveUsers = User::where('is_active', false)->get();

        $byType = User::active()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $byDepartment = User::active()
            ->whereNotNull('department')
            ->selectRaw('department, COUNT(*) as count')
            ->groupBy('department')
            ->pluck('count', 'department')
            ->toArray();

        return [
            'total_active' => $activeUsers->count(),
            'total_inactive' => $inactiveUsers->count(),
            'by_type' => $byType,
            'by_department' => $byDepartment,
            'in_probation' => $activeUsers->filter(fn($u) => $u->isInProbation())->count(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate expiring contracts report.
     */
    public function getExpiringContractsReport(int $days = 30): array
    {
        // Contracts expiring soon
        $expiringContracts = EmployeeContract::with('employee')
            ->expiringWithin($days)
            ->orderBy('end_date', 'asc')
            ->get();

        // Employees in probation ending soon
        $probationEnding = User::active()
            ->whereNotNull('date_of_works')
            ->whereNotNull('probation_period_days')
            ->get()
            ->filter(function ($user) use ($days) {
                $probationEnd = $user->getProbationEndDate();
                if (!$probationEnd) return false;

                return $probationEnd->between(now(), now()->addDays($days));
            })
            ->map(fn($user) => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'probation_end_date' => $user->getProbationEndDate()->toDateString(),
                'days_remaining' => now()->diffInDays($user->getProbationEndDate(), false),
            ])
            ->sortBy('days_remaining')
            ->values();

        return [
            'expiring_contracts' => $expiringContracts->map(fn($c) => [
                'contract_id' => $c->id,
                'user_id' => $c->employee->id,
                'employee_name' => $c->employee->name,
                'employee_email' => $c->employee->email,
                'end_date' => $c->end_date->toDateString(),
                'days_remaining' => $c->getRemainingDays(),
            ]),
            'probation_ending' => $probationEnding,
            'summary' => [
                'contracts_expiring_count' => $expiringContracts->count(),
                'probation_ending_count' => $probationEnding->count(),
                'days_checked' => $days,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate ended/expired contracts report (عقود منتهية).
     * Lists contracts that have already ended: status expired/terminated or active with end_date in the past.
     */
    public function getEndedContractsReport(?string $fromDate = null, ?string $toDate = null, ?string $status = null): array
    {
        $query = EmployeeContract::with('employee')->ended();

        if ($fromDate) {
            $query->whereDate('end_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('end_date', '<=', $toDate);
        }
        if ($status && in_array($status, ['expired', 'terminated'], true)) {
            $query->where('status', $status);
        }

        $contracts = $query->orderBy('end_date', 'desc')->get();

        $list = $contracts->map(fn($c) => [
            'contract_id' => $c->id,
            'user_id' => $c->employee?->id,
            'employee_name' => $c->employee?->name,
            'employee_email' => $c->employee?->email,
            'start_date' => $c->start_date?->toDateString(),
            'end_date' => $c->end_date?->toDateString(),
            'status' => $c->status,
            'employee_missing' => $c->employee === null,
        ]);

        return [
            'ended_contracts' => $list,
            'summary' => [
                'total_count' => $contracts->count(),
                'by_status' => $contracts->groupBy('status')->map->count()->toArray(),
            ],
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'status' => $status,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}

