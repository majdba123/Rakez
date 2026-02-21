<?php

namespace Database\Factories;

use App\Models\CommissionDistribution;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionDistributionFactory extends Factory
{
    protected $model = CommissionDistribution::class;

    public function definition(): array
    {
        $percentage = $this->faker->randomFloat(2, 5, 30);
        $amount = $this->faker->numberBetween(1000, 10000);

        return [
            'commission_id' => Commission::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                'lead_generation',
                'persuasion',
                'closing',
                'team_leader',
                'sales_manager',
                'project_manager',
            ]),
            'external_name' => null,
            'bank_account' => 'SA' . $this->faker->numerify('####################'),
            'percentage' => $percentage,
            'amount' => $amount,
            'status' => 'pending',
            'notes' => null,
        ];
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'type' => 'external_marketer',
            'external_name' => $this->faker->name,
        ]);
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
            'notes' => $this->faker->sentence,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(5),
            'paid_at' => now(),
        ]);
    }
}
