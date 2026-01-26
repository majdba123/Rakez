<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesTargetFactory extends Factory
{
    protected $model = SalesTarget::class;

    public function definition(): array
    {
        return [
            'leader_id' => User::factory(),
            'marketer_id' => User::factory(),
            'contract_id' => Contract::factory(),
            'contract_unit_id' => ContractUnit::factory(),
            'target_type' => 'reservation',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'new',
            'leader_notes' => $this->faker->sentence(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
