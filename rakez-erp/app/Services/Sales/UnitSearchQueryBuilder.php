<?php

namespace App\Services\Sales;

use App\Enums\ContractUnitWorkflowStatus;
use App\Enums\ContractWorkflowStatus;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UnitSearchQueryBuilder
{
    public function __construct(
        private UnitSearchCriteria $criteriaNormalizer
    ) {}

    public function baseQuery(): Builder
    {
        return ContractUnit::query()
            ->join('contracts', 'contract_units.contract_id', '=', 'contracts.id')
            ->whereNull('contracts.deleted_at')
            ->select('contract_units.*');
    }

    public function applyAuthorizationScope(Builder $query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $query->whereIn('contracts.id', $this->getAccessibleContractIds($user));
    }

    public function applyAuthorizationScopeRaw($query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $query->whereIn('contracts.id', $this->getAccessibleContractIds($user));
    }

    public function applyCriteria(Builder $query, array $filters): void
    {
        $filters = $this->criteriaNormalizer->normalizeForSearch($filters);

        if (! empty($filters['city_id'])) {
            $query->where('contracts.city_id', (int) $filters['city_id']);
        } elseif (! empty($filters['city'])) {
            $ids = DB::table('cities')->where('name', 'like', '%'.$filters['city'].'%')->pluck('id')->all();
            $query->whereIn('contracts.city_id', $ids ?: [0]);
        }

        if (! empty($filters['district_id'])) {
            $query->where('contracts.district_id', (int) $filters['district_id']);
        } elseif (! empty($filters['district'])) {
            $ids = DB::table('districts')->where('name', 'like', '%'.$filters['district'].'%')->pluck('id')->all();
            $query->whereIn('contracts.district_id', $ids ?: [0]);
        }

        if (! empty($filters['project_id'])) {
            $query->where('contracts.id', (int) $filters['project_id']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === ContractUnitWorkflowStatus::Available->value) {
                $this->applyStrictAvailability($query);
            } else {
                $query->where('contract_units.status', $filters['status']);
            }
        }

        if (! empty($filters['unit_type'])) {
            $query->where('contract_units.unit_type', $filters['unit_type']);
        }

        if (isset($filters['floor']) && $filters['floor'] !== '' && $filters['floor'] !== null) {
            $query->where('contract_units.floor', (string) $filters['floor']);
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== null) {
            $query->where('contract_units.price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== null) {
            $query->where('contract_units.price', '<=', $filters['max_price']);
        }

        if (isset($filters['min_bedrooms']) && $filters['min_bedrooms'] !== null) {
            $query->where('contract_units.bedrooms', '>=', $filters['min_bedrooms']);
        }

        if (isset($filters['max_bedrooms']) && $filters['max_bedrooms'] !== null) {
            $query->where('contract_units.bedrooms', '<=', $filters['max_bedrooms']);
        }

        $areaExpr = $this->areaExpression();

        if (isset($filters['min_area']) && $filters['min_area'] !== null) {
            $query->whereRaw("$areaExpr >= ?", [$filters['min_area']]);
        }

        if (isset($filters['max_area']) && $filters['max_area'] !== null) {
            $query->whereRaw("$areaExpr <= ?", [$filters['max_area']]);
        }

        if (! empty($filters['q'])) {
            $search = (string) $filters['q'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('contract_units.unit_number', 'LIKE', '%'.$search.'%')
                    ->orWhere('contracts.project_name', 'LIKE', '%'.$search.'%');
            });
        }
    }

    public function applyStrictAvailability(Builder $query): void
    {
        $query->where('contract_units.status', ContractUnitWorkflowStatus::Available->value)
            ->where('contracts.status', ContractWorkflowStatus::Completed->value)
            ->whereDoesntHave('activeSalesReservations');
    }

    public function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $sortColumnMap = [
            'price' => 'contract_units.price',
            'area' => DB::raw($this->areaExpression()),
            'bedrooms' => 'contract_units.bedrooms',
            'created_at' => 'contract_units.created_at',
        ];

        $query->orderBy($sortColumnMap[$sortBy] ?? 'contract_units.created_at', $sortDir);
    }

    public function matchesUnit(ContractUnit $unit, array $criteria): bool
    {
        $query = $this->baseQuery()->where('contract_units.id', $unit->id);
        $this->applyStrictAvailability($query);
        $this->applyCriteria($query, $criteria);

        return $query->exists();
    }

    public function areaExpression(): string
    {
        return 'COALESCE(contract_units.total_area_m2, CAST(contract_units.area AS DECIMAL(12,2)))';
    }

    private function getAccessibleContractIds(User $user): array
    {
        if ($user->hasAnyRole(['sales', 'sales_leader']) || $user->type === 'sales') {
            return Contract::where('status', ContractWorkflowStatus::Completed->value)->pluck('id')->all();
        }

        return [];
    }
}
