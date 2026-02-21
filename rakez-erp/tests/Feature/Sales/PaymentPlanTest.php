<?php

namespace Tests\Feature\Sales;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\SalesReservation;
use App\Models\ReservationPaymentInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesLeader;
    protected User $salesUser;
    protected Contract $offPlanContract;
    protected ContractUnit $unit;
    protected SalesReservation $confirmedReservation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'sales.reservations.create',
            'sales.reservations.view',
            'sales.payment-plan.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Create roles
        $salesLeaderRole = Role::firstOrCreate(['name' => 'sales_leader', 'guard_name' => 'web']);
        $salesRole = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);

        $salesLeaderRole->syncPermissions($permissions);
        $salesRole->syncPermissions(['sales.reservations.create', 'sales.reservations.view']);

        // Create users
        $this->salesLeader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $this->salesLeader->assignRole('sales_leader');

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');

        // Create off-plan contract
        $admin = User::factory()->create(['type' => 'admin']);
        $this->offPlanContract = Contract::factory()->create([
            'user_id' => $admin->id,
            'status' => 'approved',
            'is_off_plan' => true, // Off-plan project
        ]);

        $secondParty = SecondPartyData::factory()->create([
            'contract_id' => $this->offPlanContract->id,
        ]);

        $this->unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondParty->id,
            'price' => 500000,
            'status' => 'reserved',
        ]);

        // Create confirmed reservation with non-refundable down payment
        $this->confirmedReservation = SalesReservation::factory()->create([
            'contract_id' => $this->offPlanContract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
            'reservation_type' => 'confirmed_reservation',
            'down_payment_status' => 'non_refundable',
            'confirmed_at' => now(),
        ]);
    }

    public function test_sales_leader_can_create_payment_plan()
    {
        $response = $this->actingAs($this->salesLeader)
            ->postJson("/api/sales/reservations/{$this->confirmedReservation->id}/payment-plan", [
                'installments' => [
                    ['due_date' => now()->addMonths(1)->toDateString(), 'amount' => 100000, 'description' => 'الدفعة الأولى'],
                    ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => 100000, 'description' => 'الدفعة الثانية'],
                    ['due_date' => now()->addMonths(3)->toDateString(), 'amount' => 100000, 'description' => 'الدفعة الثالثة'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('reservation_payment_installments', 3);
        $this->assertDatabaseHas('reservation_payment_installments', [
            'sales_reservation_id' => $this->confirmedReservation->id,
            'amount' => 100000,
            'status' => 'pending',
        ]);
    }

    public function test_regular_sales_user_cannot_create_payment_plan()
    {
        $response = $this->actingAs($this->salesUser)
            ->postJson("/api/sales/reservations/{$this->confirmedReservation->id}/payment-plan", [
                'installments' => [
                    ['due_date' => now()->addMonths(1)->toDateString(), 'amount' => 100000],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_create_payment_plan_for_non_off_plan_project()
    {
        // Create a regular (non off-plan) contract
        $admin = User::factory()->create(['type' => 'admin']);
        $regularContract = Contract::factory()->create([
            'user_id' => $admin->id,
            'status' => 'approved',
            'is_off_plan' => false,
        ]);

        $secondParty = SecondPartyData::factory()->create([
            'contract_id' => $regularContract->id,
        ]);

        $unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondParty->id,
            'price' => 500000,
            'status' => 'reserved',
        ]);

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $regularContract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
            'down_payment_status' => 'non_refundable',
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->postJson("/api/sales/reservations/{$reservation->id}/payment-plan", [
                'installments' => [
                    ['due_date' => now()->addMonths(1)->toDateString(), 'amount' => 100000],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_create_duplicate_payment_plan()
    {
        // Create first payment plan
        ReservationPaymentInstallment::create([
            'sales_reservation_id' => $this->confirmedReservation->id,
            'due_date' => now()->addMonths(1),
            'amount' => 100000,
            'status' => 'pending',
        ]);

        // Try to create another plan
        $response = $this->actingAs($this->salesLeader)
            ->postJson("/api/sales/reservations/{$this->confirmedReservation->id}/payment-plan", [
                'installments' => [
                    ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => 100000],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_sales_leader_can_view_payment_plan()
    {
        // Create payment plan
        ReservationPaymentInstallment::create([
            'sales_reservation_id' => $this->confirmedReservation->id,
            'due_date' => now()->addMonths(1),
            'amount' => 100000,
            'description' => 'الدفعة الأولى',
            'status' => 'pending',
        ]);

        ReservationPaymentInstallment::create([
            'sales_reservation_id' => $this->confirmedReservation->id,
            'due_date' => now()->addMonths(2),
            'amount' => 100000,
            'description' => 'الدفعة الثانية',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->getJson("/api/sales/reservations/{$this->confirmedReservation->id}/payment-plan");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'installments',
                    'summary' => [
                        'total_installments',
                        'total_amount',
                        'paid_amount',
                        'pending_amount',
                    ],
                ],
            ]);

        $this->assertEquals(2, $response->json('data.summary.total_installments'));
        $this->assertEquals(200000, $response->json('data.summary.total_amount'));
        $this->assertEquals(100000, $response->json('data.summary.paid_amount'));
    }

    public function test_sales_leader_can_update_installment()
    {
        $installment = ReservationPaymentInstallment::create([
            'sales_reservation_id' => $this->confirmedReservation->id,
            'due_date' => now()->addMonths(1),
            'amount' => 100000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->putJson("/api/sales/payment-installments/{$installment->id}", [
                'status' => 'paid',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('reservation_payment_installments', [
            'id' => $installment->id,
            'status' => 'paid',
        ]);
    }

    public function test_cannot_update_already_paid_installment()
    {
        $installment = ReservationPaymentInstallment::create([
            'sales_reservation_id' => $this->confirmedReservation->id,
            'due_date' => now()->addMonths(1),
            'amount' => 100000,
            'status' => 'paid', // Already paid
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->putJson("/api/sales/payment-installments/{$installment->id}", [
                'amount' => 150000,
            ]);

        $response->assertStatus(422);
    }

    public function test_sales_leader_can_delete_pending_installment()
    {
        $installment = ReservationPaymentInstallment::create([
            'sales_reservation_id' => $this->confirmedReservation->id,
            'due_date' => now()->addMonths(1),
            'amount' => 100000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->deleteJson("/api/sales/payment-installments/{$installment->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('reservation_payment_installments', [
            'id' => $installment->id,
        ]);
    }

    public function test_cannot_delete_paid_installment()
    {
        $installment = ReservationPaymentInstallment::create([
            'sales_reservation_id' => $this->confirmedReservation->id,
            'due_date' => now()->addMonths(1),
            'amount' => 100000,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->salesLeader)
            ->deleteJson("/api/sales/payment-installments/{$installment->id}");

        $response->assertStatus(422);
    }
}

