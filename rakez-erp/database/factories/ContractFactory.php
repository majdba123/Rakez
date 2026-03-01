<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $realEstate = config('unsplash_images.real_estate', []);
        $projectImage = $realEstate ? $realEstate[array_rand($realEstate)] : $this->faker->imageUrl();

        return [
            'user_id' => User::factory(),
            'project_name' => $this->faker->words(3, true),
            'developer_name' => $this->faker->company(),
            'developer_number' => $this->faker->phoneNumber(),
            'city' => $this->faker->city(),
            'district' => $this->faker->streetName(),
            'units' => [],
            'project_image_url' => $projectImage,
            'developer_requiment' => $this->faker->sentence(),
            'status' => 'pending',
            'notes' => $this->faker->sentence(),
        ];
    }
}
