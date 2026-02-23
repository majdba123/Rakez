<?php

namespace App\Services\Dashboard;

use App\Models\ContractUnit;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * لوحة تحكم إدارة المشاريع
 * Project Management Dashboard Service
 */
class ProjectManagementDashboardService
{
    /**
     * Get units statistics (available vs sold)
     * إحصائيات الوحدات (متاحة vs مباعة)
     */
    public function getUnitsStatistics(): array
    {
        $stats = ContractUnit::select('status', DB::raw('count(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $availableCount = $stats['available'] ?? 0;
        $soldCount = $stats['sold'] ?? 0;
        $totalUnits = array_sum($stats);

        return [
            'available_units' => $availableCount,
            'sold_units' => $soldCount,
            'total_units' => $totalUnits,

        ];
    }

    /**
     * Get full dashboard statistics
     * إحصائيات لوحة التحكم الكاملة
     */
    public function getDashboardStatistics(): array
    {
        return [
            'units' => $this->getUnitsStatistics(),
        ];
    }
}

