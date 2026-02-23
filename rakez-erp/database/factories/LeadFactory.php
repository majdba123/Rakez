<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'contact_info' => $this->faker->phoneNumber(),
            'source' => $this->faker->randomElement(['Snapchat', 'Instagram', 'TikTok', 'Facebook']),
            'status' => 'new',
            'project_id' => Contract::factory(),
            'assigned_to' => User::factory(),
        ];
    }
}
