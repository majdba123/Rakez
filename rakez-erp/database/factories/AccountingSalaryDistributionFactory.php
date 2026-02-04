<?php

namespace Database\Factories;

use App\Models\AccountingSalaryDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountingSalaryDistribution>
 */
class AccountingSalaryDistributionFactory extends Factory
{
    protected $model = AccountingSalaryDistribution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseSalary = fake()->randomFloat(2, 5000, 20000);
        $totalCommissions = fake()->randomFloat(2, 0, 10000);
        
        return [
            'user_id' => User::factory(),
            'month' => fake()->numberBetween(1, 12),
            'year' => fake()->numberBetween(2023, 2026),
            'base_salary' => $baseSalary,
            'total_commissions' => $totalCommissions,
            'total_amount' => $baseSalary + $totalCommissions,
            'status' => fake()->randomElement(['pending', 'approved', 'paid']),
            'paid_at' => fake()->optional(0.5)->dateTimeThisYear(),
        ];
    }

    /**
     * Indicate that the distribution is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the distribution is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the distribution is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'paid_at' => null,
        ]);
    }

    /**
     * Set a specific month and year.
     */
    public function forMonth(int $month, int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'month' => $month,
            'year' => $year,
        ]);
    }
}
