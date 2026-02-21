<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Sales\SalesDashboardService;
use Illuminate\Support\Arr;

class KpiSalesTool implements ToolContract
{
    public function __construct(
        private readonly SalesDashboardService $dashboardService
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('sales.dashboard.view')) {
            return [
                'result' => ['error' => 'Permission denied', 'allowed' => false],
                'source_refs' => [],
            ];
        }
        $dateFrom = Arr::get($args, 'date_from');
        $dateTo = Arr::get($args, 'date_to');
        $groupBy = Arr::get($args, 'group_by', 'day');
        $scope = $groupBy === 'team' ? 'team' : 'me';

        $kpis = $this->dashboardService->getKPIs($scope, $dateFrom, $dateTo, $user);

        return [
            'result' => [
                'kpis' => $kpis,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'scope' => $scope,
            ],
            'source_refs' => [['type' => 'tool', 'title' => 'Sales KPIs', 'ref' => 'tool_kpi_sales']],
        ];
    }
}
