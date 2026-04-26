<?php

namespace App\Services\Sales;

use App\Enums\ContractUnitWorkflowStatus;
use App\Enums\ContractWorkflowStatus;
use App\Models\ContractUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SalesExecutiveDashboardService
{
    /**
     * Same notion of "available" as SalesProjectService::getAvailableUnitsCount:
     * unit status = available, completed contract, no active (negotiation/confirmed) reservations.
     */
    public function availableUnitsQuery(array $filters = []): Builder
    {
        $query = ContractUnit::query()
            ->where('contract_units.status', ContractUnitWorkflowStatus::Available->value)
            ->whereHas('contract', function (Builder $c) {
                $c->where('status', ContractWorkflowStatus::Completed->value);
            })
            ->whereDoesntHave('activeSalesReservations');

        $this->applyListFilters($query, $filters);

        return $query;
    }

    public function countAvailableByUnitType(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->select('contract_units.unit_type', DB::raw('COUNT(*) as c'))
            ->groupBy('contract_units.unit_type')
            ->get();

        $byType = [];
        foreach ($rows as $row) {
            $key = ($row->unit_type === null || $row->unit_type === '') ? '_empty' : (string) $row->unit_type;
            if (! isset($byType[$key])) {
                $byType[$key] = 0;
            }
            $byType[$key] += (int) $row->c;
        }

        ksort($byType);

        return $byType;
    }

    /**
     * Total and per-`unit_type` counts (respects the same filters as the list).
     *
     * @return array{total: int, by_type: array<string, int>, by_type_labels: array<int, array{unit_type: string, count: int}>}
     */
    public function availableStockSummary(array $filters = []): array
    {
        $base = $this->availableUnitsQuery($filters);
        $total = (clone $base)->count();
        $byType = $this->countAvailableByUnitType($base);
        $byTypeLabels = [];
        foreach ($byType as $key => $c) {
            $byTypeLabels[] = [
                'unit_type' => $key === '_empty' ? null : $key,
                'count' => $c,
            ];
        }

        return [
            'total' => $total,
            'by_type' => $byType,
            'by_type_list' => $byTypeLabels,
        ];
    }

    public function paginateAvailable(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = min(max($perPage, 1), 100);

        $query = $this->availableUnitsQuery($filters)
            ->with(['contract.city', 'contract.district']);

        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyListFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['contract_id'])) {
            $query->where('contract_units.contract_id', (int) $filters['contract_id']);
        }

        if (! empty($filters['city_id'])) {
            $query->whereHas('contract', fn (Builder $c) => $c->where('city_id', (int) $filters['city_id']));
        }

        if (! empty($filters['district_id'])) {
            $query->whereHas('contract', fn (Builder $c) => $c->where('district_id', (int) $filters['district_id']));
        }

        if (! empty($filters['unit_type'])) {
            $query->where('contract_units.unit_type', $filters['unit_type']);
        }

        if (isset($filters['floor']) && $filters['floor'] !== null && $filters['floor'] !== '') {
            $query->where('contract_units.floor', $filters['floor']);
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== null) {
            $query->where('contract_units.price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== null) {
            $query->where('contract_units.price', '<=', $filters['max_price']);
        }

        if (! empty($filters['q'])) {
            $search = (string) $filters['q'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('contract_units.unit_number', 'like', '%'.$search.'%')
                    ->orWhereHas('contract', function (Builder $c) use ($search) {
                        $c->where('project_name', 'like', '%'.$search.'%');
                    });
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'price';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        if ($sortBy === 'area') {
            $query->orderByRaw(
                'COALESCE(contract_units.total_area_m2, CAST(contract_units.area AS DECIMAL(12,2))) '.$sortDir
            );

            return;
        }

        $map = [
            'price' => 'contract_units.price',
            'unit_number' => 'contract_units.unit_number',
            'created_at' => 'contract_units.created_at',
        ];

        $column = $map[$sortBy] ?? 'contract_units.price';
        $query->orderBy($column, $sortDir);
    }
}
