<?php

namespace App\Services\Accounting;

use InvalidArgumentException;

class ProjectCommissionCalculator
{
    public const BUYER_FORMULA_KEY = 'buyer_unit_price_times_rate';

    public const OWNER_FORMULA_KEY = 'owner_unit_price_divided_by_100_percent_plus_rate';

    /**
     * @return array{
     *   source: string,
     *   base_amount: float,
     *   commission_percentage: float,
     *   formula_key: string,
     *   commission_amount: float,
     * }
     */
    public function calculateBuyer(float $amount, float $percentage): array
    {
        $pct = round($percentage, 4);
        $commissionAmount = round($amount * $pct / 100, 2);

        return [
            'source' => 'buyer',
            'base_amount' => round($amount, 2),
            'commission_percentage' => $pct,
            'formula_key' => self::BUYER_FORMULA_KEY,
            'commission_amount' => $commissionAmount,
        ];
    }

    /**
     * @return array{
     *   source: string,
     *   base_amount: float,
     *   commission_percentage: float,
     *   formula_key: string,
     *   commission_amount: float,
     * }
     */
    public function calculateOwner(float $amount, float $percentage): array
    {
        $pct = round($percentage, 4);
        $denominator = 1 + $pct / 100;
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Invalid commission_percentage for owner source (denominator <= 0).');
        }

        $commissionAmount = round($amount / $denominator, 2);

        return [
            'source' => 'owner',
            'base_amount' => round($amount, 2),
            'commission_percentage' => $pct,
            'formula_key' => self::OWNER_FORMULA_KEY,
            'commission_amount' => $commissionAmount,
        ];
    }

    /**
     * @return array{
     *   source: string,
     *   base_amount: float,
     *   commission_percentage: float,
     *   formula_key: string,
     *   commission_amount: float,
     * }
     */
    public function calculate(string $source, float $amount, float $percentage): array
    {
        $normalized = strtolower(trim($source));

        return match ($normalized) {
            'buyer' => $this->calculateBuyer($amount, $percentage),
            'owner' => $this->calculateOwner($amount, $percentage),
            default => throw new InvalidArgumentException("Unsupported commission_source: {$source}. Use buyer or owner."),
        };
    }
}
