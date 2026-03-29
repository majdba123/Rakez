<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

class DistrictFactory extends Factory
{
    protected $model = District::class;

    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'name' => 'حي ' . $this->faker->unique()->word(),
        ];
    }
}
