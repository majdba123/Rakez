<?php

namespace Tests\Feature\Credit;

use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreditBookingCreditActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $creditUser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['credit.bookings.view', 'credit.bookings.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.bookings.view', 'credit.bookings.manage']);

        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');
    }

    public function test_credit_user_can_log_client_contact_action(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/actions", [
                'notes' => 'تم الاتصال بالعميل',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action_type', 'credit_client_contact');

        $this->assertDatabaseHas('sales_reservation_actions', [
            'sales_reservation_id' => $reservation->id,
            'user_id' => $this->creditUser->id,
            'action_type' => 'credit_client_contact',
        ]);

        $action = SalesReservationAction::where('sales_reservation_id', $reservation->id)->first();
        $this->assertSame('تم الاتصال بالعميل', $action->notes);
    }

    public function test_sales_user_cannot_use_credit_actions_route(): void
    {
        Permission::firstOrCreate(['name' => 'sales.reservations.view', 'guard_name' => 'web']);
        $salesRole = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $salesRole->givePermissionTo('sales.reservations.view');

        $salesUser = User::factory()->create(['type' => 'sales']);
        $salesUser->assignRole('sales');

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'marketing_employee_id' => $salesUser->id,
        ]);

        $response = $this->actingAs($salesUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/actions", [
                'notes' => 'x',
            ]);

        $response->assertForbidden();
    }

    public function test_credit_action_forbidden_without_manage_permission(): void
    {
        $viewRole = Role::create(['name' => 'credit_view_only_actions', 'guard_name' => 'web']);
        $viewRole->syncPermissions(['credit.bookings.view']);

        $viewer = User::factory()->create(['type' => 'credit']);
        $viewer->assignRole('credit_view_only_actions');

        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $this->actingAs($viewer)
            ->postJson("/api/credit/bookings/{$reservation->id}/actions", [])
            ->assertForbidden();
    }
}
