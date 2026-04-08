<?php

namespace Tests\Feature\Credit;

use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CreditBookingController::logCreditClientContact exists but POST api/credit/bookings/{id}/actions
 * is not registered in routes/api.php. Unmatched paths return 404 before credit role middleware runs.
 */
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

    public function test_post_booking_actions_returns_not_found(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/actions", [
                'notes' => 'Client contacted',
            ])
            ->assertNotFound();
    }

    public function test_sales_user_receives_not_found_for_booking_actions_path(): void
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

        $this->actingAs($salesUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/actions", [
                'notes' => 'x',
            ])
            ->assertNotFound();
    }

    public function test_view_only_credit_user_receives_not_found_for_booking_actions_path(): void
    {
        $viewRole = Role::create(['name' => 'credit_view_only_actions', 'guard_name' => 'web']);
        $viewRole->syncPermissions(['credit.bookings.view']);

        $viewer = User::factory()->create(['type' => 'credit']);
        $viewer->assignRole('credit_view_only_actions');

        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $this->actingAs($viewer)
            ->postJson("/api/credit/bookings/{$reservation->id}/actions", [])
            ->assertNotFound();
    }
}
