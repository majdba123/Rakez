<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_name' => fake()->sentence(3),
            'team_id' => Team::factory(),
            'due_at' => now()->addDays(7),
            'assigned_to' => User::factory(),
            'status' => Task::STATUS_IN_PROGRESS,
            'cannot_complete_reason' => null,
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_COMPLETED,
        ]);
    }

    public function couldNotComplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_COULD_NOT_COMPLETE,
            'cannot_complete_reason' => fake()->sentence(),
        ]);
    }
}
