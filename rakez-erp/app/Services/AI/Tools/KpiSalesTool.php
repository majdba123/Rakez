<?php

namespace App\Services\AI\Tools;

use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Support\Carbon;

class KpiSalesTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('sales.dashboard.view')) {
            return ToolResponse::denied('sales.dashboard.view');
        }

        $dateFrom = isset($args['date_from']) ? Carbon::parse($args['date_from']) : null;
        $dateTo = isset($args['date_to']) ? Carbon::parse($args['date_to']) : now();
        $groupBy = $args['group_by'] ?? null;

        $query = SalesReservation::query();

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        $query->where('created_at', '<=', $dateTo);

        $total = (clone $query)->count();
        $confirmed = (clone $query)->where('status', 'confirmed')->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $cancelled = (clone $query)->where('status', 'cancelled')->count();

        $data = [
            'summary' => 'Organization-wide reservation counts for users with sales.dashboard.view (not scoped to a single employee unless filtered elsewhere).',
            'warnings' => [
                'Counts include all sales_reservations visible to this permission scope (typically org-wide dashboard metrics).',
            ],
            'period' => [
                'from' => $dateFrom?->toDateString() ?? 'all_time',
                'to' => $dateTo->toDateString(),
            ],
            'total_reservations' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'confirmation_rate' => $total > 0 ? round(($confirmed / $total) * 100, 2) : 0,
        ];

        if ($groupBy === 'day') {
            $data['by_day'] = (clone $query)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at)')
                ->pluck('count', 'date')
                ->toArray();
        }

        if ($groupBy === 'team') {
            $data['by_team'] = (clone $query)
                ->selectRaw('marketing_employee_id, COUNT(*) as count')
                ->groupBy('marketing_employee_id')
                ->pluck('count', 'marketing_employee_id')
                ->toArray();
        }

        return ToolResponse::success('tool_kpi_sales', $args, $data, [
            ['type' => 'tool', 'title' => 'Sales KPI Report', 'ref' => 'kpi:sales'],
        ]);
    }
}
