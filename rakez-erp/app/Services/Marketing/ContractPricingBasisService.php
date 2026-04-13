<?php

namespace App\Services\Marketing;

use App\Models\Contract;

/**
 * Resolves canonical commission-base pricing for marketing APIs (calculate-budget, developer plan contract block).
 *
 * Priority for {@see self::COMMISSION_BASE_KEY}:
 * 1. total_unit_price_override (or legacy unit_price) when positive
 * 2. Sum of contract unit prices where status = available
 * 3. Stored contract_infos.avg_property_value (legacy single-scalar fallback)
 */
class ContractPricingBasisService
{
    public const COMMISSION_BASE_KEY = 'total_unit_price';

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

        $averageUnitPrice = $availableCount > 0 ? round($totalAvailableSum / $availableCount, 2) : 0.0;
        $averageUnitPriceAll = $allCount > 0 ? round($totalAllSum / $allCount, 2) : 0.0;

        $override = $this->readOverride($inputs);

        $source = 'none';
        $commissionBase = 0.0;

        if ($override !== null && $override > 0) {
            $source = 'total_unit_price_override';
            $commissionBase = $override;
        } elseif ($totalAvailableSum > 0) {
            $source = 'unit_prices_sum_available';
            $commissionBase = $totalAvailableSum;
        } elseif ($avgStored > 0) {
            $source = 'avg_property_value_stored';
            $commissionBase = $avgStored;
        }

        $basis = [
            'source' => $source,
            self::COMMISSION_BASE_KEY => round($commissionBase, 2),
            'commission_base_amount' => round($commissionBase, 2),
            'total_unit_price_available_sum' => round($totalAvailableSum, 2),
            'total_unit_price_all_sum' => round($totalAllSum, 2),
            'available_units_count' => $availableCount,
            'all_units_count' => $allCount,
            'average_unit_price' => $averageUnitPrice,
            'average_unit_price_all' => $averageUnitPriceAll,
            'avg_property_value_stored' => round($avgStored, 2),
            'override_applied' => $override !== null && $override > 0,
        ];

        return $basis;
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
