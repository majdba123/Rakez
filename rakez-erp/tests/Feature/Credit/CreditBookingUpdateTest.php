<?php

namespace Tests\Feature\Credit;

use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

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

    public function test_patch_updates_client_fields_on_confirmed_booking(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'credit_status' => 'pending',
            'client_name' => 'Old',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}", [
                'client_name' => 'New Name',
                'client_mobile' => '0500000000',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client.name', 'New Name')
            ->assertJsonPath('data.client.mobile', '0500000000');

        $this->assertSame('New Name', $reservation->fresh()->client_name);
    }

    public function test_patch_requires_at_least_one_field(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}", []);

        $response->assertStatus(422);
    }

    public function test_patch_rejects_non_confirmed_booking(): void
    {
        $reservation = SalesReservation::factory()->underNegotiation()->create();

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}", [
                'client_name' => 'X',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_patch_rejects_sold_credit_status(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'credit_status' => 'sold',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/bookings/{$reservation->id}", [
                'client_name' => 'X',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_patch_forbidden_without_manage_permission(): void
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
            ->assertForbidden();
    }
}
