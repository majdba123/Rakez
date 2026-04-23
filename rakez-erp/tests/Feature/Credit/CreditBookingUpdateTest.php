<?php

namespace Tests\Feature\Credit;

use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CreditBookingController::update exists but PATCH api/credit/bookings/{id} is not registered in routes/api.php
 * (only GET bookings/{id} is). These tests assert current HTTP behaviour.
 */
class CreditBookingUpdateTest extends TestCase
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

    public function test_patch_booking_by_id_returns_method_not_allowed(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'credit_status' => 'pending',
            'client_name' => 'Old',
        ]);

        $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}", [
                'client_name' => 'New Name',
                'client_mobile' => '0500000000',
            ])
            ->assertStatus(405);
    }

    public function test_patch_empty_body_still_returns_method_not_allowed(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}", [])
            ->assertStatus(405);
    }

    public function test_patch_for_view_only_role_returns_method_not_allowed(): void
    {
        $viewRole = Role::create(['name' => 'credit_view_only_patch', 'guard_name' => 'web']);
        $viewRole->syncPermissions(['credit.bookings.view']);

        $viewer = User::factory()->create(['type' => 'credit']);
        $viewer->assignRole('credit_view_only_patch');

        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $this->actingAs($viewer)
            ->patchJson("/api/credit/bookings/{$reservation->id}", [
                'client_name' => 'X',
            ])
            ->assertStatus(405);
    }
}
