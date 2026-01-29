<?php

namespace Database\Factories;

use App\Models\TitleTransfer;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TitleTransfer>
 */
class TitleTransferFactory extends Factory
{
    protected $model = TitleTransfer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_reservation_id' => SalesReservation::factory(),
            'processed_by' => User::factory(),
            'status' => 'pending',
        ];
    }

    /**
     * In preparation status.
     */
    public function inPreparation(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'preparation',
        ]);
    }

    /**
     * Scheduled status.
     */
    public function scheduled(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_date' => now()->addDays($this->faker->numberBetween(3, 14)),
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'scheduled_date' => now()->subDays($this->faker->numberBetween(1, 7)),
            'completed_date' => now(),
        ]);
    }
}

