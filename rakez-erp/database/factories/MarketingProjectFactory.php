<?php

namespace Database\Factories;

use App\Models\MarketingProject;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingProjectFactory extends Factory
{
    protected $model = MarketingProject::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'status' => $this->faker->randomElement(['active', 'completed', 'on_hold']),
            'assigned_team_leader' => User::factory(),
        ];
    }
}
