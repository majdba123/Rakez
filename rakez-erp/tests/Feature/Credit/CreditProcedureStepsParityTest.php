<?php

namespace Tests\Feature\Credit;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\CreditFinancingTracker;
use App\Models\SalesReservation;
use App\Models\TitleTransfer;
use App\Models\User;
use App\Support\Credit\CreditProcessStepBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class CreditProcedureStepsParityTest extends BasePermissionTestCase
{
    #[Test]
    public function credit_booking_show_endpoint_uses_canonical_credit_process_step_builder(): void
    {
        $admin = $this->createAdmin([
            'is_active' => true,
            'email' => 'credit-parity-admin@example.com',
        ]);

        $marketer = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $marketer->syncRolesFromType();

        $contract = Contract::factory()->create();
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
        ]);

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $marketer->id,
            'status' => 'confirmed',
            'credit_status' => 'title_transfer',
            'purchase_mechanism' => 'supported_bank',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'stage_1_status' => 'completed',
            'stage_2_status' => 'in_progress',
            'stage_3_status' => 'pending',
            'stage_4_status' => 'pending',
            'stage_5_status' => 'pending',
        ]);

        TitleTransfer::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'status' => 'scheduled',
            'scheduled_date' => now()->addDay()->toDateString(),
        ]);

        $reservation = $reservation->fresh(['financingTracker', 'titleTransfer']);

        $expected = app(CreditProcessStepBuilder::class)->creditProcedureStepsForApi($reservation);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson("/api/credit/bookings/{$reservation->id}")
            ->assertOk();

        $this->assertSame($expected, $response->json('data.credit_procedure_steps'));
    }
}

