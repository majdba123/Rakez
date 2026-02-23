<?php

namespace Database\Factories;

use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeContract>
 */
class EmployeeContractFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmployeeContract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now');
        $endDate = fake()->dateTimeBetween($startDate, '+2 years');

        return [
            'user_id' => User::factory(),
            'contract_data' => [
                'job_title' => fake()->jobTitle(),
                'department' => fake()->randomElement(['Sales', 'Marketing', 'HR', 'IT']),
                'salary' => fake()->numberBetween(3000, 20000),
                'work_type' => fake()->randomElement(['full_time', 'part_time', 'contract']),
                'probation_period' => '90 days',
                'terms' => fake()->paragraphs(3, true),
                'benefits' => fake()->sentences(5, true),
            ],
            'pdf_path' => null,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'status' => fake()->randomElement(['draft', 'active']),
        ];
    }

    /**
     * Indicate that the contract is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the contract is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the contract is expired.
     */
    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'expired',
            'end_date' => fake()->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the contract is expiring soon.
     */
    public function expiringSoon(int $days = 15): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
            'end_date' => now()->addDays($days)->format('Y-m-d'),
        ]);
    }
}

