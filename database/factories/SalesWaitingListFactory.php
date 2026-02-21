<?php

namespace Database\Factories;

use App\Models\SalesWaitingList;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesWaitingListFactory extends Factory
{
    protected $model = SalesWaitingList::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'contract_unit_id' => ContractUnit::factory(),
            'sales_staff_id' => User::factory(),
            'client_name' => $this->faker->name(),
            'client_mobile' => $this->faker->numerify('05########'),
            'client_email' => $this->faker->safeEmail(),
            'priority' => $this->faker->numberBetween(1, 10),
            'status' => 'waiting',
            'notes' => $this->faker->optional()->sentence(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'converted',
            'converted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDays(1),
        ]);
    }
}
