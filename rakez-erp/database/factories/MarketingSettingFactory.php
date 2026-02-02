<?php

namespace Database\Factories;

use App\Models\MarketingSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingSettingFactory extends Factory
{
    protected $model = MarketingSetting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
            'value' => $this->faker->word,
            'description' => $this->faker->sentence,
        ];
    }
}
