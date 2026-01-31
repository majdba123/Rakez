<?php

namespace Database\Factories;

use App\Models\CreditFinancingTracker;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditFinancingTracker>
 */
class CreditFinancingTrackerFactory extends Factory
{
    protected $model = CreditFinancingTracker::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();
        
        return [
            'sales_reservation_id' => SalesReservation::factory(),
            'assigned_to' => User::factory(),
            'stage_1_status' => 'in_progress',
            'stage_1_deadline' => $now->copy()->addHours(48),
            'stage_2_status' => 'pending',
            'stage_3_status' => 'pending',
            'stage_4_status' => 'pending',
            'stage_5_status' => 'pending',
            'is_supported_bank' => false,
            'overall_status' => 'in_progress',
        ];
    }

    /**
     * Stage 1 completed.
     */
    public function stage1Completed(): Factory
    {
        $now = now();
        return $this->state(fn (array $attributes) => [
            'stage_1_status' => 'completed',
            'bank_name' => $this->faker->company(),
            'client_salary' => $this->faker->numberBetween(5000, 50000),
            'employment_type' => $this->faker->randomElement(['government', 'private']),
            'stage_1_completed_at' => $now,
            'stage_2_status' => 'in_progress',
            'stage_2_deadline' => $now->copy()->addDays(5),
        ]);
    }

    /**
     * All stages completed.
     */
    public function completed(): Factory
    {
        $now = now();
        return $this->state(fn (array $attributes) => [
            'stage_1_status' => 'completed',
            'bank_name' => $this->faker->company(),
            'client_salary' => $this->faker->numberBetween(5000, 50000),
            'employment_type' => $this->faker->randomElement(['government', 'private']),
            'stage_1_completed_at' => $now->copy()->subDays(15),
            'stage_2_status' => 'completed',
            'stage_2_completed_at' => $now->copy()->subDays(10),
            'stage_3_status' => 'completed',
            'stage_3_completed_at' => $now->copy()->subDays(7),
            'stage_4_status' => 'completed',
            'appraiser_name' => $this->faker->name(),
            'stage_4_completed_at' => $now->copy()->subDays(5),
            'stage_5_status' => 'completed',
            'stage_5_completed_at' => $now,
            'overall_status' => 'completed',
            'completed_at' => $now,
        ]);
    }

    /**
     * Supported bank (adds 5 extra days).
     */
    public function supportedBank(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'is_supported_bank' => true,
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'overall_status' => 'rejected',
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * With overdue stage.
     */
    public function withOverdueStage(int $stage = 1): Factory
    {
        return $this->state(fn (array $attributes) => [
            "stage_{$stage}_status" => 'overdue',
            "stage_{$stage}_deadline" => now()->subHours(24),
        ]);
    }
}



