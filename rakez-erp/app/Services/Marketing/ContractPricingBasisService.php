<?php

namespace App\Services\Marketing;

use App\Models\Contract;

/**
 * Resolves canonical commission-base pricing for marketing APIs (project `pricing_source`, developer plan show, budget calculator inputs).
 *
 * Canonical commission base = **available-unit inventory value** (sum of available contract unit prices only).
 *
 * Priority for {@see self::COMMISSION_BASE_KEY}:
 * 1. total_unit_price_override (or legacy unit_price) when positive
 * 2. Sum of **available** contract unit prices (`unit_prices_sum_available`)
 * 3. Stored contract_infos.avg_property_value (legacy fallback when no available unit rows / zero sum)
 *
 * Informational fields `total_unit_price_all_sum`, `all_units_count`, and `average_unit_price_all`
 * are still returned for transparency but do not drive planning or commission math.
 */
class ContractPricingBasisService
{
    public const COMMISSION_BASE_KEY = 'total_unit_price';

    public const SOURCE_OVERRIDE = 'total_unit_price_override';

    public const SOURCE_AVAILABLE_UNITS = 'unit_prices_sum_available';

    /** @deprecated Use SOURCE_AVAILABLE_UNITS. Kept for migration reference only. */
    public const SOURCE_ALL_UNITS = 'unit_prices_sum_available';

    public const SOURCE_AVG_STORED = 'avg_property_value_stored';

    public const SOURCE_NONE = 'none';

    /**
     * @param  array<string, mixed>  $inputs  total_unit_price_override, unit_price (deprecated alias)
     * @return array<string, mixed>
     */
    public function resolve(Contract $contract, array $inputs = []): array
    {
        $contract->loadMissing(['info', 'contractUnits']);
        $info = $contract->info;
        $units = $contract->contractUnits;

        $availableUnits = $units->filter(fn ($u) => $u->status === 'available');
        $allCount = $units->count();
        $availableCount = $availableUnits->count();

        $totalAvailableSum = (float) $availableUnits->sum('price');
        $totalAllSum = (float) $units->sum('price');

        $avgStored = $info ? (float) ($info->avg_property_value ?? 0) : 0.0;

        $averageUnitPriceAvailable = $availableCount > 0 ? round($totalAvailableSum / $availableCount, 2) : 0.0;
        $averageUnitPriceAll = $allCount > 0 ? round($totalAllSum / $allCount, 2) : 0.0;

        $override = $this->readOverride($inputs);

        $source = self::SOURCE_NONE;
        $commissionBase = 0.0;

        if ($override !== null && $override > 0) {
            $source = self::SOURCE_OVERRIDE;
            $commissionBase = $override;
        } elseif ($totalAvailableSum > 0) {
            $source = self::SOURCE_AVAILABLE_UNITS;
            $commissionBase = $totalAvailableSum;
        } elseif ($avgStored > 0) {
            $source = self::SOURCE_AVG_STORED;
            $commissionBase = $avgStored;
        }

        return [
            'source' => $source,
            self::COMMISSION_BASE_KEY => round($commissionBase, 2),
            'commission_base_amount' => round($commissionBase, 2),
            'total_unit_price_available_sum' => round($totalAvailableSum, 2),
            'total_unit_price_all_sum' => round($totalAllSum, 2),
            'available_units_count' => $availableCount,
            'all_units_count' => $allCount,
            /** Mean price for units currently marked available (informational). */
            'average_unit_price_available' => $averageUnitPriceAvailable,
            /** Mean price across all units (aligns with project-wide total / count). */
            'average_unit_price_all' => $averageUnitPriceAll,
            /** Canonical UI average = mean price of available units (matches planning commission base). */
            'average_unit_price' => $averageUnitPriceAvailable,
            'avg_property_value_stored' => round($avgStored, 2),
            'override_applied' => $override !== null && $override > 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function readOverride(array $inputs): ?float
    {
        if (array_key_exists('total_unit_price_override', $inputs) && $inputs['total_unit_price_override'] !== null && $inputs['total_unit_price_override'] !== '') {
            return (float) $inputs['total_unit_price_override'];
        }
        if (array_key_exists('unit_price', $inputs) && $inputs['unit_price'] !== null && $inputs['unit_price'] !== '') {
            return (float) $inputs['unit_price'];
        }

        return null;
    }
}
