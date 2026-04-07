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
            'stage_1_deadline' => $now->copy()->addDays(CreditFinancingTracker::STAGE_DURATION_DAYS[1]),
            'stage_2_status' => 'pending',
            'stage_3_status' => 'pending',
            'stage_4_status' => 'pending',
            'stage_5_status' => 'pending',
            'stage_6_status' => 'pending',
            'is_supported_bank' => false,
            'is_cash_workflow' => false,
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
            'stage_2_deadline' => $now->copy()->addDays(CreditFinancingTracker::STAGE_DURATION_DAYS[2]),
        ]);
    }

    /**
     * All six stages completed (financing workflow finished; title transfer may follow).
     */
    public function completed(): Factory
    {
        $now = now();
        $supported = $this->faker->boolean();

        return $this->state(fn (array $attributes) => [
            'is_cash_workflow' => false,
            'is_supported_bank' => $supported,
            'stage_1_status' => 'completed',
            'bank_name' => $this->faker->company(),
            'client_salary' => $this->faker->numberBetween(5000, 50000),
            'employment_type' => $this->faker->randomElement(['government', 'private']),
            'stage_1_completed_at' => $now->copy()->subDays(20),
            'stage_2_status' => 'completed',
            'stage_2_completed_at' => $now->copy()->subDays(17),
            'stage_3_status' => 'completed',
            'stage_3_completed_at' => $now->copy()->subDays(14),
            'stage_4_status' => 'completed',
            'appraiser_name' => $this->faker->name(),
            'stage_4_completed_at' => $now->copy()->subDays(11),
            'stage_5_status' => 'completed',
            'stage_5_completed_at' => $now->copy()->subDays(5),
            'stage_6_status' => 'completed',
            'stage_6_completed_at' => $now,
            'overall_status' => 'completed',
            'completed_at' => $now,
        ]);
    }

    /**
     * Supported bank (affects stage 6 duration only in live workflow).
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
            "stage_{$stage}_deadline" => now()->subDay(),
        ]);
    }

    /**
     * Stages 1–5 completed; stage 6 active (financing not finished — title transfer not allowed yet).
     */
    public function atStage6InProgress(bool $supportedBank = true): Factory
    {
        $now = now();

        return $this->state(fn (array $attributes) => [
            'is_supported_bank' => $supportedBank,
            'is_cash_workflow' => false,
            'stage_1_status' => 'completed',
            'stage_1_completed_at' => $now->copy()->subDays(12),
            'stage_2_status' => 'completed',
            'stage_2_completed_at' => $now->copy()->subDays(10),
            'stage_3_status' => 'completed',
            'stage_3_completed_at' => $now->copy()->subDays(8),
            'stage_4_status' => 'completed',
            'appraiser_name' => $this->faker->name(),
            'stage_4_completed_at' => $now->copy()->subDays(6),
            'stage_5_status' => 'completed',
            'stage_5_completed_at' => $now->copy()->subHour(),
            'stage_6_status' => 'in_progress',
            'stage_6_deadline' => $now->copy()->addDays(
                CreditFinancingTracker::durationDaysForStage(6, $supportedBank, false)
            ),
            'overall_status' => 'in_progress',
        ]);
    }

    /**
     * Cash purchase: stages 2–5 auto-completed; stage 1 active (matches service initialization).
     */
    public function cashPurchaseInProgress(): Factory
    {
        $now = now();

        return $this->state(fn (array $attributes) => [
            'is_cash_workflow' => true,
            'is_supported_bank' => false,
            'stage_1_status' => 'in_progress',
            'stage_1_deadline' => $now->copy()->addDays(CreditFinancingTracker::CASH_STAGE_1_DAYS),
            'stage_2_status' => 'completed',
            'stage_2_completed_at' => $now,
            'stage_3_status' => 'completed',
            'stage_3_completed_at' => $now,
            'stage_4_status' => 'completed',
            'stage_4_completed_at' => $now,
            'stage_5_status' => 'completed',
            'stage_5_completed_at' => $now,
            'stage_6_status' => 'pending',
            'overall_status' => 'in_progress',
        ]);
    }

    /**
     * Cash purchase workflow fully completed (title transfer may follow).
     */
    public function cashPurchaseCompleted(): Factory
    {
        $now = now();

        return $this->state(fn (array $attributes) => [
            'is_cash_workflow' => true,
            'is_supported_bank' => false,
            'stage_1_status' => 'completed',
            'stage_1_completed_at' => $now->copy()->subDays(5),
            'stage_2_status' => 'completed',
            'stage_2_completed_at' => $now->copy()->subDays(5),
            'stage_3_status' => 'completed',
            'stage_3_completed_at' => $now->copy()->subDays(5),
            'stage_4_status' => 'completed',
            'stage_4_completed_at' => $now->copy()->subDays(5),
            'stage_5_status' => 'completed',
            'stage_5_completed_at' => $now->copy()->subDays(5),
            'stage_6_status' => 'completed',
            'stage_6_completed_at' => $now,
            'overall_status' => 'completed',
            'completed_at' => $now,
        ]);
    }
}
