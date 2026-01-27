<?php

namespace Database\Factories;

use App\Models\Lead;
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
        ];
    }
}
