<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\MarketingProject;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    /**
     * Get inventory dashboard data: marketing projects count, units stats, and optionally pending contracts count.
     *
     * @param  array{include_pending_count?: bool}  $params  Optional. include_pending_count (default true) adds pending_contracts_count to the result.
     * @return array{marketing_projects_count: int, units_stats: array, pending_contracts_count?: int}
     */
    public function getDashboardData(array $params = []): array
    {
        $includePendingCount = $params['include_pending_count'] ?? true;

        $marketingProjectsCount = MarketingProject::count();

        $unitRows = DB::table('contract_units')
            ->join('second_party_data', 'second_party_data.id', '=', 'contract_units.second_party_data_id')
            ->join('contracts', 'contracts.id', '=', 'second_party_data.contract_id')
            ->whereNull('contract_units.deleted_at')
            ->whereNull('second_party_data.deleted_at')
            ->whereNull('contracts.deleted_at')
            ->groupBy('contract_units.status', 'contract_units.unit_type')
            ->select([
                'contract_units.status as unit_status',
                'contract_units.unit_type as unit_type',
                DB::raw('COUNT(contract_units.id) as total_units'),
            ])
            ->get();

        $totalUnits = 0;
        $byStatus = [];
        $byType = [];

        foreach ($unitRows as $row) {
            $status = (string) ($row->unit_status ?? 'unknown');
            $type = (string) ($row->unit_type ?? 'unknown');
            $count = (int) $row->total_units;

            $totalUnits += $count;

            $byStatus[$status]['total'] = ($byStatus[$status]['total'] ?? 0) + $count;
            $byStatus[$status]['by_type'][$type] = ($byStatus[$status]['by_type'][$type] ?? 0) + $count;

            $byType[$type]['total'] = ($byType[$type]['total'] ?? 0) + $count;
            $byType[$type]['by_status'][$status] = ($byType[$type]['by_status'][$status] ?? 0) + $count;
        }

        $data = [
            'marketing_projects_count' => $marketingProjectsCount,
            'units_stats' => [
                'total_units' => $totalUnits,
                'by_status' => $byStatus,
                'by_type' => $byType,
            ],
        ];

        if ($includePendingCount) {
            $data['pending_contracts_count'] = $this->getPendingContractsCount();
        }

        return $data;
    }

    /**
     * Count contracts with status 'pending' (not soft-deleted).
     */
    public function getPendingContractsCount(): int
    {
        return Contract::pending()->count();
    }
}
