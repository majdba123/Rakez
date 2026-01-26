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
            'client_name' => $this->faker->name(),
            'client_mobile' => '05' . $this->faker->numerify('########'),
            'client_nationality' => 'Saudi',
            'client_iban' => 'SA' . $this->faker->numerify('######################'),
            'payment_method' => 'cash',
            'down_payment_amount' => $this->faker->randomFloat(2, 10000, 500000),
            'down_payment_status' => 'non_refundable',
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
