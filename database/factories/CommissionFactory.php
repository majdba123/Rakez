<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        $finalSellingPrice = $this->faker->numberBetween(500000, 2000000);
        $commissionPercentage = $this->faker->randomFloat(2, 1, 5);
        $totalAmount = ($finalSellingPrice * $commissionPercentage) / 100;
        $vat = ($totalAmount * 15) / 100;
        $marketingExpenses = $this->faker->numberBetween(0, 5000);
        $bankFees = $this->faker->numberBetween(0, 1000);
        $netAmount = $totalAmount - $vat - $marketingExpenses - $bankFees;

        return [
            'contract_unit_id' => ContractUnit::factory(),
            'sales_reservation_id' => SalesReservation::factory(),
            'final_selling_price' => $finalSellingPrice,
            'commission_percentage' => $commissionPercentage,
            'total_amount' => $totalAmount,
            'vat' => $vat,
            'marketing_expenses' => $marketingExpenses,
            'bank_fees' => $bankFees,
            'net_amount' => $netAmount,
            'commission_source' => $this->faker->randomElement(['owner', 'buyer']),
            'team_responsible' => $this->faker->optional()->word,
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'approved_at' => now()->subDays(5),
            'paid_at' => now(),
        ]);
    }
}
