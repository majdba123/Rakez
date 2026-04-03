<?php

namespace Tests\Feature\Credit;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\ReservationPaymentInstallment;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreditPaymentPlanRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected User $creditUser;

    protected SalesReservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['credit.bookings.view', 'credit.payment_plan.manage', 'sales.payment-plan.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.bookings.view', 'credit.payment_plan.manage']);

        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');

        $admin = User::factory()->create(['type' => 'admin']);
        $offPlan = Contract::factory()->create([
            'user_id' => $admin->id,
            'status' => 'completed',
            'is_off_plan' => true,
        ]);

        SecondPartyData::factory()->create(['contract_id' => $offPlan->id]);

        $unit = ContractUnit::factory()->create([
            'contract_id' => $offPlan->id,
            'price' => 500000,
            'status' => 'reserved',
        ]);

        $marketer = User::factory()->create(['type' => 'sales']);
        $this->reservation = SalesReservation::factory()->create([
            'contract_id' => $offPlan->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $marketer->id,
            'status' => 'confirmed',
            'reservation_type' => 'confirmed_reservation',
            'down_payment_status' => 'non_refundable',
            'payment_method' => 'cash',
            'confirmed_at' => now(),
        ]);
    }

    public function test_credit_user_can_get_payment_plan_via_credit_route(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->getJson("/api/credit/bookings/{$this->reservation->id}/payment-plan");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_credit_user_can_create_payment_plan_via_credit_route(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$this->reservation->id}/payment-plan", [
                'installments' => [
                    ['due_date' => now()->addMonth()->toDateString(), 'amount' => 100000, 'description' => 'دفعة 1'],
                    ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => 100000, 'description' => 'دفعة 2'],
                ],
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame(2, ReservationPaymentInstallment::where('sales_reservation_id', $this->reservation->id)->count());
    }

    public function test_credit_user_gets_403_on_sales_payment_plan_route(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->getJson("/api/sales/reservations/{$this->reservation->id}/payment-plan");

        $response->assertForbidden();
    }

    public function test_credit_user_can_update_and_delete_installment_via_credit_routes(): void
    {
        $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$this->reservation->id}/payment-plan", [
                'installments' => [
                    ['due_date' => now()->addMonth()->toDateString(), 'amount' => 50000, 'description' => 'دفعة'],
                ],
            ])
            ->assertStatus(201);

        $installment = ReservationPaymentInstallment::where('sales_reservation_id', $this->reservation->id)->first();
        $this->assertNotNull($installment);

        $this->actingAs($this->creditUser)
            ->putJson("/api/credit/payment-installments/{$installment->id}", [
                'amount' => 60000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEqualsWithDelta(60000.0, (float) $installment->fresh()->amount, 0.01);

        $this->actingAs($this->creditUser)
            ->deleteJson("/api/credit/payment-installments/{$installment->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('reservation_payment_installments', ['id' => $installment->id]);
    }
}
