<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class TasksSeeder extends Seeder
{
    public function run(): void
    {
        $teamIds = Team::query()->pluck('id')->all();
        if (empty($teamIds)) {
            $teamIds = Team::factory()->count(3)->create()->pluck('id')->all();
        }

        $userIds = User::query()->pluck('id')->all();
        if (empty($userIds)) {
            $userIds = User::factory()->count(10)->create()->pluck('id')->all();
        }

        $statuses = [
            Task::STATUS_IN_PROGRESS,
            Task::STATUS_COMPLETED,
            Task::STATUS_COULD_NOT_COMPLETE,
        ];

        for ($i = 0; $i < 40; $i++) {
            $status = Arr::random($statuses);

            Task::query()->create([
                'task_name' => fake()->sentence(4),
                'team_id' => Arr::random($teamIds),
                'due_at' => now()->addDays(fake()->numberBetween(1, 15))->setTime(fake()->numberBetween(8, 18), fake()->randomElement([0, 15, 30, 45])),
                'assigned_to' => Arr::random($userIds),
                'status' => $status,
                'cannot_complete_reason' => $status === Task::STATUS_COULD_NOT_COMPLETE ? fake()->sentence(8) : null,
                'created_by' => Arr::random($userIds),
            ]);
        }
    }
}
