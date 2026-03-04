<?php

namespace App\Services\Sales;

use App\Models\ContractUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SalesUnitSearchService
{
    public function search(array $filters, User $user): LengthAwarePaginator
    {
        $query = ContractUnit::query()
            ->join('second_party_data', 'contract_units.second_party_data_id', '=', 'second_party_data.id')
            ->join('contracts', 'second_party_data.contract_id', '=', 'contracts.id')
            ->whereNull('contracts.deleted_at')
            ->whereNull('second_party_data.deleted_at')
            ->select('contract_units.*');

        $this->applyAuthorizationScope($query, $user);
        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = min(max($perPage, 1), 100);

        $units = $query->paginate($perPage);

        $units->load('secondPartyData.contract');

        return $units;
    }

    public function getAvailableFilters(User $user): array
    {
        $query = DB::table('contract_units')
            ->join('second_party_data', 'contract_units.second_party_data_id', '=', 'second_party_data.id')
            ->join('contracts', 'second_party_data.contract_id', '=', 'contracts.id')
            ->whereNull('contract_units.deleted_at')
            ->whereNull('contracts.deleted_at')
            ->whereNull('second_party_data.deleted_at');

        $this->applyAuthorizationScopeRaw($query, $user);

        $cities = (clone $query)
            ->whereNotNull('contracts.city')
            ->where('contracts.city', '!=', '')
            ->distinct()
            ->pluck('contracts.city')
            ->sort()
            ->values()
            ->all();

        $districtsRaw = (clone $query)
            ->whereNotNull('contracts.city')
            ->where('contracts.city', '!=', '')
            ->whereNotNull('contracts.district')
            ->where('contracts.district', '!=', '')
            ->select('contracts.city', 'contracts.district')
            ->distinct()
            ->get();

        $districts = [];
        foreach ($districtsRaw as $row) {
            $districts[$row->city][] = $row->district;
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

        $areaExpr = 'COALESCE(contract_units.total_area_m2, CAST(contract_units.area AS DECIMAL(12,2)))';
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
            'cities'         => $cities,
            'districts'      => $districts,
            'unit_types'     => $unitTypes,
            'bedrooms_range' => [
                'min' => $bedroomsRange?->min_val ? (int) $bedroomsRange->min_val : null,
                'max' => $bedroomsRange?->max_val ? (int) $bedroomsRange->max_val : null,
            ],
            'area_range'     => [
                'min' => $areaRange?->min_val ? round((float) $areaRange->min_val, 2) : null,
                'max' => $areaRange?->max_val ? round((float) $areaRange->max_val, 2) : null,
            ],
            'price_range'    => [
                'min' => $priceRange?->min_val ? (float) $priceRange->min_val : null,
                'max' => $priceRange?->max_val ? (float) $priceRange->max_val : null,
            ],
            'statuses'       => $statuses,
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['city'])) {
            $query->where('contracts.city', 'LIKE', '%' . $filters['city'] . '%');
        }

        if (!empty($filters['district'])) {
            $query->where('contracts.district', 'LIKE', '%' . $filters['district'] . '%');
        }

        if (!empty($filters['project_id'])) {
            $query->where('contracts.id', $filters['project_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('contract_units.status', $filters['status']);
        }

        if (!empty($filters['unit_type'])) {
            $query->where('contract_units.unit_type', $filters['unit_type']);
        }

        if (isset($filters['floor']) && $filters['floor'] !== '' && $filters['floor'] !== null) {
            $query->where('contract_units.floor', $filters['floor']);
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

        $areaExpr = 'COALESCE(contract_units.total_area_m2, CAST(contract_units.area AS DECIMAL(12,2)))';

        if (isset($filters['min_area']) && $filters['min_area'] !== null) {
            $query->whereRaw("$areaExpr >= ?", [$filters['min_area']]);
        }

        if (isset($filters['max_area']) && $filters['max_area'] !== null) {
            $query->whereRaw("$areaExpr <= ?", [$filters['max_area']]);
        }

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('contract_units.unit_number', 'LIKE', '%' . $search . '%')
                  ->orWhere('contracts.project_name', 'LIKE', '%' . $search . '%');
            });
        }
    }

    protected function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $sortColumnMap = [
            'price'      => 'contract_units.price',
            'area'       => DB::raw('COALESCE(contract_units.total_area_m2, CAST(contract_units.area AS DECIMAL(12,2)))'),
            'bedrooms'   => 'contract_units.bedrooms',
            'created_at' => 'contract_units.created_at',
        ];

        $column = $sortColumnMap[$sortBy] ?? 'contract_units.created_at';
        $query->orderBy($column, $sortDir);
    }

    /**
     * Apply authorization scope to an Eloquent Builder (used in search).
     * Admin sees all; sales_leader sees assigned + team projects; sales staff sees team projects.
     */
    protected function applyAuthorizationScope(Builder $query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $accessibleContractIds = $this->getAccessibleContractIds($user);

        $query->whereIn('contracts.id', $accessibleContractIds);
    }

    /**
     * Apply authorization scope to a raw DB query builder (used in filters).
     */
    protected function applyAuthorizationScopeRaw($query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $accessibleContractIds = $this->getAccessibleContractIds($user);

        $query->whereIn('contracts.id', $accessibleContractIds);
    }

    /**
     * Replicates the same access logic used in SalesProjectService::applyScopeFilter
     * to determine which contract IDs the user can access.
     */
    protected function getAccessibleContractIds(User $user): array
    {
        $leaderIds = collect();

        if ($user->hasRole('sales_leader')) {
            $leaderIds->push($user->id);
            if ($user->team_id) {
                $leaderIds = $leaderIds->merge(
                    User::where('team_id', $user->team_id)
                        ->where('is_manager', true)
                        ->pluck('id')
                );
            }
        } elseif ($user->team_id) {
            $leaderIds = User::where('team_id', $user->team_id)
                ->where('is_manager', true)
                ->pluck('id');
        }

        if ($leaderIds->isEmpty()) {
            return [];
        }

        $today = now()->toDateString();

        return \App\Models\SalesProjectAssignment::whereIn('leader_id', $leaderIds->unique())
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->pluck('contract_id')
            ->unique()
            ->values()
            ->all();
    }
}
