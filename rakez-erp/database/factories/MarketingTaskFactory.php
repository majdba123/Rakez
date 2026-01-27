<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\MarketingTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingTaskFactory extends Factory
{
    protected $model = MarketingTask::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'task_name' => $this->faker->sentence(3),
            'marketer_id' => User::factory(),
            'participating_marketers_count' => 4,
            'design_link' => $this->faker->url(),
            'design_number' => 'D-' . $this->faker->numerify('###'),
            'design_description' => $this->faker->paragraph(),
            'status' => 'new',
            'created_by' => User::factory(),
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
