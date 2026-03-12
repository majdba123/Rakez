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

        $projects = ['مشروع سكني', 'برج سكني', 'مجمع فلل', 'عمارة وحدات'];
        $developers = ['شركة الراجحي للتطوير', 'مؤسسة دار الأركان', 'مجموعة إعمار العقارية'];
        return [
            'user_id' => User::factory(),
            'project_name' => $projects[array_rand($projects)] . ' ' . $this->faker->numberBetween(1, 99),
            'developer_name' => $developers[array_rand($developers)],
            'developer_number' => '+966' . $this->faker->numerify('########'),
            'city' => $this->faker->randomElement(['الرياض', 'جدة', 'الدمام']),
            'district' => $this->faker->randomElement(['حي النخيل', 'حي العليا', 'حي الشفا']),
            'units' => [],
            'project_image_url' => $projectImage,
            'developer_requiment' => $this->faker->sentence(),
            'status' => 'pending',
            'notes' => $this->faker->sentence(),
            'commission_percent' => $this->faker->randomFloat(2, 2, 4),
            'commission_from' => $this->faker->randomElement(['المالك', 'المشتري', 'الطرفين']),
        ];
    }
}
