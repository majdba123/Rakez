<?php

namespace Database\Factories;

use App\Models\SalesUnitSearchAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesUnitSearchAlertFactory extends Factory
{
    protected $model = SalesUnitSearchAlert::class;

    public function definition(): array
    {
        return [
            'sales_staff_id' => User::factory(),
            'client_name' => $this->faker->name(),
            'client_mobile' => '+9665'.$this->faker->numerify('########'),
            'client_email' => $this->faker->safeEmail(),
            'client_sms_opt_in' => false,
            'status' => SalesUnitSearchAlert::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30),
        ];
    }
}
