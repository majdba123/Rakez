<?php

namespace Database\Factories;

use App\Models\ClaimFile;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimFile>
 */
class ClaimFileFactory extends Factory
{
    protected $model = ClaimFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_reservation_id' => SalesReservation::factory(),
            'generated_by' => User::factory(),
            'file_data' => [
                'project_name' => $this->faker->company(),
                'unit_number' => $this->faker->unique()->numerify('Unit-###'),
                'unit_type' => $this->faker->randomElement(['apartment', 'villa', 'office']),
                'client_name' => $this->faker->name(),
                'client_mobile' => $this->faker->phoneNumber(),
                'client_nationality' => $this->faker->country(),
                'down_payment_amount' => $this->faker->numberBetween(10000, 100000),
                'brokerage_commission_percent' => $this->faker->randomFloat(2, 1, 5),
                'tax_amount' => $this->faker->numberBetween(1000, 10000),
            ],
        ];
    }

    /**
     * With PDF generated.
     */
    public function withPdf(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'pdf_path' => 'claim_files/claim_' . $this->faker->unique()->randomNumber(5) . '.pdf',
        ]);
    }
}



