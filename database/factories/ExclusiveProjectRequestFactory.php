<?php

namespace Database\Factories;

use App\Models\ExclusiveProjectRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExclusiveProjectRequestFactory extends Factory
{
    protected $model = ExclusiveProjectRequest::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'project_name' => $this->faker->company() . ' Project',
            'developer_name' => $this->faker->company(),
            'developer_contact' => $this->faker->numerify('05########'),
            'project_description' => $this->faker->optional()->paragraph(),
            'estimated_units' => $this->faker->numberBetween(50, 500),
            'location_city' => $this->faker->randomElement(['Riyadh', 'Jeddah', 'Dammam', 'Mecca', 'Medina']),
            'location_district' => $this->faker->optional()->streetName(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'contract_completed',
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(5),
            'contract_completed_at' => now(),
        ]);
    }
}
