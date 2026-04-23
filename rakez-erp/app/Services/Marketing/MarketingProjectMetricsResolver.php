<?php

namespace App\Services\Marketing;

use App\Models\Contract;
use Illuminate\Support\Collection;

/**
 * Canonical metrics resolver for marketing projects.
 *
 * Ensures list and show endpoints return numerically identical metrics for shared fields:
 * - contract_id, project_name, status, commission_percent
 * - units_count (total, available, pending, reserved, sold, by_status)
 * - avg_unit_price (from available units across the contract)
 * - total_available_value (from ONLY available units)
 *
 * Business Rules:
 * 1. avg_unit_price = mean price of available units
 * 2. total_available_value = sum of ONLY available unit prices
 * 3. units_count exposes all known status buckets without changing pricing math
 */
class MarketingProjectMetricsResolver
{
    private const UNIT_STATUS_BUCKETS = [
        'available',
        'pending',
        'reserved',
        'sold',
    ];

    /**
     * Resolve shared metrics for a contract.
     *
     * @param Contract $contract
     * @return array<string, mixed>
     */
    public function resolve(Contract $contract): array
    {
        $contract->loadMissing(['info', 'contractUnits']);

        $units = $contract->contractUnits;
        $availableUnits = $units->where('status', 'available');
        $unitCounts = $this->unitStatusCounts($units);

        // avg_unit_price follows the marketing pricing basis: available units only.
        $availableUnitsCount = $availableUnits->count();
        $availableUnitsSum = (float) $availableUnits->sum('price');
        $avgUnitPrice = $availableUnitsCount > 0 ? round($availableUnitsSum / $availableUnitsCount, 2) : 0.0;

        // total_available_value: sum of ONLY available units
        $totalAvailableValue = (float) $availableUnits->sum('price');

        return [
            'contract_id' => (int) $contract->id,
            'project_name' => $contract->project_name,
            'status' => $contract->status,
            'commission_percent' => (float) $contract->getEffectiveCommissionPercent(),
            'units_count' => $unitCounts,
            'avg_unit_price' => $avgUnitPrice,
            'total_available_value' => $totalAvailableValue,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $units
     * @return array<string, mixed>
     */
    public function unitStatusCounts(Collection $units): array
    {
        $rawCounts = $units
            ->groupBy(fn ($unit) => strtolower((string) ($unit->status ?: 'unknown')))
            ->map(fn (Collection $items) => $items->count())
            ->all();

        $byStatus = [];
        foreach (self::UNIT_STATUS_BUCKETS as $status) {
            $byStatus[$status] = (int) ($rawCounts[$status] ?? 0);
            unset($rawCounts[$status]);
        }

        ksort($rawCounts);
        foreach ($rawCounts as $status => $count) {
            $byStatus[$status] = (int) $count;
        }

        return [
            'total' => $units->count(),
            'available' => $byStatus['available'],
            'pending' => $byStatus['pending'],
            'reserved' => $byStatus['reserved'],
            'sold' => $byStatus['sold'],
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get only the shared metric fields (for list view).
     *
     * @param Contract $contract
     * @return array<string, mixed>
     */
    public function resolveForList(Contract $contract): array
    {
        return $this->resolve($contract);
    }

    /**
     * Get shared metrics plus detail-specific fields (for show view).
     *
     * @param Contract $contract
     * @return array<string, mixed>
     */
    public function resolveForShow(Contract $contract): array
    {
        $metrics = $this->resolve($contract);

        // Add detail-specific information
        $contract->loadMissing(['info']);
        $info = $contract->info;

        return array_merge($metrics, [
            'contract_number' => $info?->contract_number ?? null,
            'advertiser' => [
                'source' => 'contract_infos.agency_number',
                'agency_number' => $info?->agency_number ?? null,
                'availability_status' => (! empty($info?->agency_number)) ? 'available' : 'pending',
            ],
            'advertiser_number' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'advertiser_number_value' => $info?->agency_number ?? null,
            'advertiser_number_status' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
        ]);
    }

    /**
     * Compute pricing basis breakdown (all unit statuses).
     *
     * @param Contract $contract
     * @return array<string, mixed>
     */
    public function resolvePricingBasis(Contract $contract): array
    {
        $contract->loadMissing(['contractUnits']);

        $units = $contract->contractUnits;
        $availableUnits = $units->where('status', 'available');

        $allUnitsCount = $units->count();
        $allUnitsSum = (float) $units->sum('price');
        $availableUnitsSum = (float) $availableUnits->sum('price');
        $availableUnitsCount = $availableUnits->count();

        $avgUnitPriceAll = $allUnitsCount > 0 ? round($allUnitsSum / $allUnitsCount, 2) : 0.0;
        $avgUnitPriceAvailable = $availableUnitsCount > 0 ? round($availableUnitsSum / $availableUnitsCount, 2) : 0.0;

        return [
            'total_unit_price' => round($availableUnitsSum, 2),
            'total_unit_price_available_sum' => round($availableUnitsSum, 2),
            'total_unit_price_all_sum' => round($allUnitsSum, 2),
            'available_units_count' => $availableUnitsCount,
            'all_units_count' => $allUnitsCount,
            'average_unit_price' => $avgUnitPriceAll,
            'average_unit_price_all' => $avgUnitPriceAll,
            'average_unit_price_available' => $avgUnitPriceAvailable,
        ];
    }
}
