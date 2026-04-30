<?php

namespace App\Services\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SalesUnitSearchService
{
    public function __construct(
        private UnitSearchQueryBuilder $searchQueryBuilder
    ) {}

    public function search(array $filters, User $user): LengthAwarePaginator
    {
        $query = $this->searchQueryBuilder->baseQuery();

        $this->searchQueryBuilder->applyAuthorizationScope($query, $user);
        $this->searchQueryBuilder->applyCriteria($query, $filters);
        $this->searchQueryBuilder->applySorting($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = min(max($perPage, 1), 100);

        $units = $query->paginate($perPage);

        $units->load(['contract.city', 'contract.district']);

        return $units;
    }

    public function getAvailableFilters(User $user): array
    {
        $query = DB::table('contract_units')
            ->join('contracts', 'contract_units.contract_id', '=', 'contracts.id')
            ->whereNull('contract_units.deleted_at')
            ->whereNull('contracts.deleted_at');

        $this->applyAuthorizationScopeRaw($query, $user);

        $cities = (clone $query)
            ->join('cities', 'contracts.city_id', '=', 'cities.id')
            ->whereNotNull('contracts.city_id')
            ->distinct()
            ->orderBy('cities.name')
            ->pluck('cities.name')
            ->values()
            ->all();

        $districtsRaw = (clone $query)
            ->join('cities', 'contracts.city_id', '=', 'cities.id')
            ->join('districts', 'contracts.district_id', '=', 'districts.id')
            ->whereNotNull('contracts.city_id')
            ->whereNotNull('contracts.district_id')
            ->select('cities.name as city_name', 'districts.name as district_name')
            ->distinct()
            ->get();

        $districts = [];
        foreach ($districtsRaw as $row) {
            $districts[$row->city_name][] = $row->district_name;
        }
        foreach ($districts as &$districtList) {
            sort($districtList);
            $districtList = array_values(array_unique($districtList));
        }
        unset($districtList);

        $unitTypes = (clone $query)
            ->whereNotNull('contract_units.unit_type')
            ->where('contract_units.unit_type', '!=', '')
            ->distinct()
            ->pluck('contract_units.unit_type')
            ->sort()
            ->values()
            ->all();

        $bedroomsRange = (clone $query)
            ->whereNotNull('contract_units.bedrooms')
            ->selectRaw('MIN(contract_units.bedrooms) as min_val, MAX(contract_units.bedrooms) as max_val')
            ->first();

        $areaExpr = $this->searchQueryBuilder->areaExpression();
        $areaRange = (clone $query)
            ->whereRaw("$areaExpr IS NOT NULL AND $areaExpr > 0")
            ->selectRaw("MIN($areaExpr) as min_val, MAX($areaExpr) as max_val")
            ->first();

        $priceRange = (clone $query)
            ->where('contract_units.price', '>', 0)
            ->selectRaw('MIN(contract_units.price) as min_val, MAX(contract_units.price) as max_val')
            ->first();

        $statuses = (clone $query)
            ->whereNotNull('contract_units.status')
            ->where('contract_units.status', '!=', '')
            ->distinct()
            ->pluck('contract_units.status')
            ->sort()
            ->values()
            ->all();

        return [
            'cities' => $cities,
            'districts' => $districts,
            'unit_types' => $unitTypes,
            'bedrooms_range' => [
                'min' => $bedroomsRange?->min_val ? (int) $bedroomsRange->min_val : null,
                'max' => $bedroomsRange?->max_val ? (int) $bedroomsRange->max_val : null,
            ],
            'area_range' => [
                'min' => $areaRange?->min_val ? round((float) $areaRange->min_val, 2) : null,
                'max' => $areaRange?->max_val ? round((float) $areaRange->max_val, 2) : null,
            ],
            'price_range' => [
                'min' => $priceRange?->min_val ? (float) $priceRange->min_val : null,
                'max' => $priceRange?->max_val ? (float) $priceRange->max_val : null,
            ],
            'statuses' => $statuses,
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $this->searchQueryBuilder->applyCriteria($query, $filters);
    }

    protected function applySorting(Builder $query, array $filters): void
    {
        $this->searchQueryBuilder->applySorting($query, $filters);
    }

    /**
     * Apply authorization scope to an Eloquent Builder (used in search).
     * Admin sees all; sales_leader sees assigned + team projects; sales staff sees team projects.
     */
    protected function applyAuthorizationScope(Builder $query, User $user): void
    {
        $this->searchQueryBuilder->applyAuthorizationScope($query, $user);
    }

    /**
     * Apply authorization scope to a raw DB query builder (used in filters).
     */
    protected function applyAuthorizationScopeRaw($query, User $user): void
    {
        $this->searchQueryBuilder->applyAuthorizationScopeRaw($query, $user);
    }
}
