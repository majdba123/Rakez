<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesReservationFactory extends Factory
{
    protected $model = SalesReservation::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'contract_unit_id' => ContractUnit::factory(),
            'marketing_employee_id' => User::factory(),
            'status' => 'confirmed',
            'reservation_type' => 'confirmed_reservation',
            'contract_date' => now()->format('Y-m-d'),
            'negotiation_notes' => null,
            'negotiation_reason' => null,
            'proposed_price' => null,
            'evacuation_date' => null,
            'approval_deadline' => null,
            'client_name' => $this->faker->name(), // required for credit/bookings list
            'client_mobile' => '05' . $this->faker->numerify('########'),
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA' . $this->faker->numerify('######################'),
            'payment_method' => 'cash',
            'down_payment_amount' => $this->faker->randomFloat(2, 10000, 500000),
            'down_payment_status' => 'non_refundable',
            'down_payment_confirmed' => false,
            'down_payment_confirmed_by' => null,
            'down_payment_confirmed_at' => null,
            'brokerage_commission_percent' => null,
            'commission_payer' => null,
            'tax_amount' => null,
            'credit_status' => 'pending',
            'purchase_mechanism' => 'cash',
            'voucher_pdf_path' => null,
            'snapshot' => [
                'project' => ['name' => 'Test Project'],
                'unit' => ['number' => 'A-101'],
                'employee' => ['name' => 'Test Employee'],
            ],
            'confirmed_at' => now(),
            'cancelled_at' => null,
        ];
    }

    public function underNegotiation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
            'negotiation_notes' => $this->faker->sentence(),
            'negotiation_reason' => 'السعر',
            'proposed_price' => $this->faker->randomFloat(2, 100000, 400000),
            'approval_deadline' => now()->addHours(48),
            'confirmed_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
