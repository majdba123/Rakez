<?php

namespace App\Services\Dashboard;

use App\Models\ContractUnit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * HR Dashboard Service
 * Dashboard data builder for HR module
 */
class DashboardService
{
    /**
     * HR dashboard statistics
     */
    public function getHrDashboardStatistics(): array
    {
        return [
            'employees' => $this->getEmployeeStats(),
            'salary' => [
                'average_employee_payment' => $this->getAverageEmployeePayment(),
            ],
            'units' => $this->getUnitsStats(),
        ];
    }

    /**
     * Employee statistics (cached)
     */
    protected function getEmployeeStats(): array
    {
        return Cache::remember('hr_dashboard_employee_stats_v1', 30, function () {
            $totalEmployees = User::count();

            return [
                'total_employees' => $totalEmployees,
            ];
        });
    }

    /**
     * Average employee salary (payment).
     * Uses active employees only, excludes NULL salary.
     */
    protected function getAverageEmployeePayment(): float
    {
        return (float) Cache::remember('hr_dashboard_avg_salary_v1', 30, function () {
            return (float) User::whereNull('deleted_at')
                ->whereNotNull('salary')
                ->avg('salary');
        });
    }

    /**
     * Contract units stats (sold/total) + sold per employee.
     */
    protected function getUnitsStats(): array
    {
        return Cache::remember('hr_dashboard_units_stats_v1', 30, function () {
            $totalUnits = ContractUnit::whereNull('deleted_at')->count();
            $soldUnits = ContractUnit::whereNull('deleted_at')->where('status', 'sold')->count();

            // Use active employees for ratio (avoid divide-by-zero)
            $activeEmployees = User::whereNull('deleted_at')->count();
            $soldUnitsPerEmployee = $activeEmployees > 0 ? ($soldUnits / $activeEmployees) : 0.0;

            return [
                'total_all_units' => $totalUnits,
                'sold_units' => $soldUnits,
                'sold_units_per_employee' => $soldUnitsPerEmployee,
            ];
        });
    }
}


