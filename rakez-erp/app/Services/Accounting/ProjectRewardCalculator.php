<?php

namespace App\Services\Accounting;

use App\Models\ProjectRewardSetting;
use App\Models\SalesReservation;
use Illuminate\Validation\ValidationException;

class ProjectRewardCalculator
{
    public function resolveSaleAmount(SalesReservation $reservation): float
    {
        $reservation->loadMissing('contractUnit');

        $proposedPrice = $reservation->proposed_price !== null ? (float) $reservation->proposed_price : 0.0;
        if ($proposedPrice > 0) {
            return round($proposedPrice, 2);
        }

        $unitPrice = $reservation->contractUnit?->price !== null ? (float) $reservation->contractUnit->price : 0.0;
        if ($unitPrice > 0) {
            return round($unitPrice, 2);
        }

        throw ValidationException::withMessages([
            'sale_amount' => 'Unable to resolve sale amount from reservation proposed_price or contract unit price.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateBaseAmount(
        string $mode,
        ?float $manualAmount,
        ?float $rewardPercentage,
        SalesReservation $reservation,
    ): array {
        if ($mode === ProjectRewardSetting::MODE_MANUAL_AMOUNT) {
            if ($manualAmount === null || $manualAmount <= 0) {
                throw ValidationException::withMessages([
                    'manual_amount' => 'The manual_amount field is required when calculation_mode is manual_amount.',
                ]);
            }

            $baseAmount = round($manualAmount, 2);

            return [
                'calculation_mode' => $mode,
                'calculation_base_amount' => $baseAmount,
                'reward_percentage' => null,
                'base_amount' => $baseAmount,
            ];
        }

        if ($mode !== ProjectRewardSetting::MODE_PERCENTAGE_OF_SALE) {
            throw ValidationException::withMessages([
                'calculation_mode' => 'Unsupported reward calculation mode.',
            ]);
        }

        if ($rewardPercentage === null || $rewardPercentage <= 0) {
            throw ValidationException::withMessages([
                'reward_percentage' => 'The reward_percentage field is required when calculation_mode is percentage_of_sale.',
            ]);
        }

        $saleAmount = $this->resolveSaleAmount($reservation);
        $baseAmount = round($saleAmount * $rewardPercentage / 100, 2);

        return [
            'calculation_mode' => $mode,
            'calculation_base_amount' => $saleAmount,
            'reward_percentage' => round($rewardPercentage, 4),
            'base_amount' => $baseAmount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateVat(float $baseAmount, bool $taxEnabled, float $vatPercentage = 15): array
    {
        $baseAmount = round($baseAmount, 2);
        $vatPercentage = round($vatPercentage, 4);
        $vatAmount = $taxEnabled ? round($baseAmount * $vatPercentage / 100, 2) : 0.0;

        return [
            'tax_enabled' => $taxEnabled,
            'vat_percentage' => $vatPercentage,
            'vat_amount' => $vatAmount,
            'total_amount' => round($baseAmount + $vatAmount, 2),
            'distribution_pool_amount' => $baseAmount,
        ];
    }
}
