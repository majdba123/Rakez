<?php

namespace Database\Factories;

use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\Contract;
use App\Models\ContractUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepositFactory extends Factory
{
    protected $model = Deposit::class;

    public function definition(): array
    {
        return [
            'sales_reservation_id' => SalesReservation::factory(),
            'contract_id' => Contract::factory(),
            'contract_unit_id' => ContractUnit::factory(),
            'amount' => $this->faker->numberBetween(1000, 50000),
            'payment_method' => $this->faker->randomElement(['bank_transfer', 'cash', 'bank_financing']),
            'client_name' => $this->faker->name,
            'payment_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'commission_source' => $this->faker->randomElement(['owner', 'buyer']),
            'status' => 'pending',
            'notes' => $this->faker->optional()->sentence,
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'received',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_by' => \App\Models\User::factory(),
            'confirmed_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refunded_at' => now(),
            'commission_source' => 'owner', // Only owner deposits can be refunded
        ]);
    }

    public function ownerSource(): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_source' => 'owner',
        ]);
    }

    public function buyerSource(): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_source' => 'buyer',
        ]);
    }
}
