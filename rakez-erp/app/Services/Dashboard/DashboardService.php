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
            'units' => $this->getUnitsStats(),
        ];
    }


    protected function getEmployeeStats(): array
    {
        return Cache::remember('hr_dashboard_employee_stats_v1', 30, function () {
            $totalEmployees = User::count();

            return [
                'total_employees' => $totalEmployees,
            ];
        });
    }



    protected function getUnitsStats(): array
    {
        return Cache::remember('hr_dashboard_units_stats_v1', 30, function () {
            $totalUnits = ContractUnit::count();
            $soldUnits = ContractUnit::where('status', 'sold')->count();

            // Ratios (avoid divide-by-zero)
            $allEmployees = User::count();
            $salesEmployees = User::where('type', 'sales')->count(); // "sell" in UI == "sales" in DB

            $soldUnitsPerSalesEmployee = $salesEmployees > 0 ? ($soldUnits / $salesEmployees) : 0.0;

            return [
                'total_all_units' => $totalUnits,
                'sales_employees_count' => $salesEmployees,
                'sold_units' => $soldUnits,
                'sold_units_per_sales_employee' => $soldUnitsPerSalesEmployee,
            ];
        });
    }
}


