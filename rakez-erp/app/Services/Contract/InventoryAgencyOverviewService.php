<?php

namespace App\Services\Contract;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryAgencyOverviewService
{
    public function __construct(
        protected ContractService $contractService
    ) {
    }

    /**
     * Build full agency overview data: contract rows with units_stats, price/area ranges, and color.
     * Returns ['data' => array, 'meta' => array] for the paginated response.
     */
    public function getOverviewData(array $filters, int $perPage): array
    {
        $rows = $this->contractService->getContractsAgencyOverviewForAdmin($filters, $perPage);

        $contractIds = collect($rows->items())->pluck('contract_id')->map(fn ($v) => (int) $v)->all();

        $priceAreaRanges = $this->getContractUnitsPriceAndAreaRanges($contractIds);
        $unitAgg = $this->getContractUnitsAggregation($contractIds);

        $data = collect($rows->items())->map(function ($row) use ($unitAgg, $priceAreaRanges) {
            $agencyDate = $row->agency_date ? Carbon::parse($row->agency_date) : null;
            $remainingDays = $agencyDate ? now()->diffInDays($agencyDate, false) : null;

            $color = 'gray';
            if ($remainingDays !== null) {
                if ($remainingDays > 90) {
                    $color = 'green';
                } elseif ($remainingDays > 30) {
                    $color = 'yellow';
                } else {
                    $color = 'red';
                }
            }

            $cid = (int) $row->contract_id;
            $ranges = $priceAreaRanges[$cid] ?? null;

            return [
                'contract_id' => $cid,
                'project_name' => $row->project_name,
                'status' => $row->status,
                'location_url' => $row->location_url,
                'agency_date' => $agencyDate?->toDateString(),
                'color' => $color,
                'unit_price_range' => $ranges ? [
                    'min_price' => $ranges['min_price'],
                    'max_price' => $ranges['max_price'],
                ] : null,
                'unit_area_range' => $ranges ? [
                    'min_area' => $ranges['min_area'],
                    'max_area' => $ranges['max_area'],
                ] : null,
                'units_stats' => $unitAgg[$cid] ?? [
                    'total' => 0,
                    'by_status' => [],
                ],
            ];
        });

        return [
            'data' => $data,
            'meta' => [
                'total' => $rows->total(),
                'count' => $rows->count(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ];
    }

    /**
     * For each contract, get min/max price and min/max area (total_area_m2) of its contract units.
     * Returns array keyed by contract_id with min_price, max_price, min_area, max_area.
     */
    public function getContractUnitsPriceAndAreaRanges(array $contractIds): array
    {
        if (empty($contractIds)) {
            return [];
        }

        $rows = DB::table('contract_units')
            ->join('second_party_data', 'second_party_data.id', '=', 'contract_units.second_party_data_id')
            ->whereNull('contract_units.deleted_at')
            ->whereNull('second_party_data.deleted_at')
            ->whereIn('second_party_data.contract_id', $contractIds)
            ->groupBy('second_party_data.contract_id')
            ->select([
                'second_party_data.contract_id as contract_id',
                DB::raw('MIN(contract_units.price) as min_price'),
                DB::raw('MAX(contract_units.price) as max_price'),
                DB::raw('MIN(contract_units.total_area_m2) as min_area'),
                DB::raw('MAX(contract_units.total_area_m2) as max_area'),
            ])
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $cid = (int) $r->contract_id;
            $result[$cid] = [
                'min_price' => $r->min_price !== null ? (float) $r->min_price : null,
                'max_price' => $r->max_price !== null ? (float) $r->max_price : null,
                'min_area' => $r->min_area !== null ? (float) $r->min_area : null,
                'max_area' => $r->max_area !== null ? (float) $r->max_area : null,
            ];
        }

        return $result;
    }

    /**
     * Aggregate contract units by contract_id, status and unit_type (counts).
     * Returns array keyed by contract_id with 'total' and 'by_status' => [status => ['total' => n, 'by_type' => [type => n]]].
     */
    public function getContractUnitsAggregation(array $contractIds): array
    {
        if (empty($contractIds)) {
            return [];
        }

        $unitRows = DB::table('contract_units')
            ->join('second_party_data', 'second_party_data.id', '=', 'contract_units.second_party_data_id')
            ->whereNull('contract_units.deleted_at')
            ->whereNull('second_party_data.deleted_at')
            ->whereIn('second_party_data.contract_id', $contractIds)
            ->groupBy('second_party_data.contract_id', 'contract_units.status', 'contract_units.unit_type')
            ->select([
                'second_party_data.contract_id as contract_id',
                'contract_units.status as unit_status',
                'contract_units.unit_type as unit_type',
                DB::raw('COUNT(contract_units.id) as total_count'),
            ])
            ->get();

        $unitAgg = [];
        foreach ($unitRows as $r) {
            $cid = (int) $r->contract_id;
            $status = (string) ($r->unit_status ?? 'unknown');
            $type = (string) ($r->unit_type ?? 'unknown');
            $count = (int) $r->total_count;

            $unitAgg[$cid]['total'] = ($unitAgg[$cid]['total'] ?? 0) + $count;
            $unitAgg[$cid]['by_status'][$status]['total'] = ($unitAgg[$cid]['by_status'][$status]['total'] ?? 0) + $count;
            $unitAgg[$cid]['by_status'][$status]['by_type'][$type] = ($unitAgg[$cid]['by_status'][$status]['by_type'][$type] ?? 0) + $count;
        }

        return $unitAgg;
    }
}
