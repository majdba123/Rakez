<?php

namespace Tests\Feature\Credit;

use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class AccountingConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use TestsWithPermissions;

    protected User $accountingUser;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $depositPerms = ['accounting.deposits.view', 'accounting.deposits.manage'];

        $this->createRoleWithPermissions('accounting', $depositPerms);
        $this->createRoleWithPermissions('admin', $depositPerms);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');

        $this->adminUser = User::factory()->create(['type' => 'admin']);
        $this->adminUser->assignRole('admin');
    }

    public function test_accounting_user_can_list_pending_confirmations(): void
    {
        SalesReservation::factory()->count(3)->create([
            'status' => 'confirmed',
            'payment_method' => 'bank_transfer',
            'down_payment_confirmed' => false,
        ]);

        SalesReservation::factory()->create([
            'status' => 'confirmed',
            'payment_method' => 'cash',
            'down_payment_confirmed' => false,
        ]);

        $response = $this->actingAs($this->accountingUser)
            ->getJson('/api/accounting/pending-confirmations');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_accounting_user_can_confirm_down_payment(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'payment_method' => 'bank_transfer',
            'down_payment_confirmed' => false,
        ]);

        $response = $this->actingAs($this->accountingUser)
            ->postJson("/api/accounting/confirmations/{$reservation->id}/confirm");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'down_payment_confirmed' => true,
            'down_payment_confirmed_by' => $this->accountingUser->id,
        ]);
    }

    public function test_cannot_confirm_already_confirmed_payment(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'payment_method' => 'bank_transfer',
            'down_payment_confirmed' => true,
        ]);

        $response = $this->actingAs($this->accountingUser)
            ->postJson("/api/accounting/confirmations/{$reservation->id}/confirm");

        $response->assertStatus(400);
    }

    public function test_cannot_confirm_cash_payment(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'payment_method' => 'cash',
            'down_payment_confirmed' => false,
        ]);

        $response = $this->actingAs($this->accountingUser)
            ->postJson("/api/accounting/confirmations/{$reservation->id}/confirm");

        $response->assertStatus(400);
    }

    public function test_can_view_confirmation_history(): void
    {
        SalesReservation::factory()->count(5)->create([
            'status' => 'confirmed',
            'payment_method' => 'bank_transfer',
            'down_payment_confirmed' => true,
            'down_payment_confirmed_by' => $this->accountingUser->id,
            'down_payment_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->accountingUser)
            ->getJson('/api/accounting/confirmations/history');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_admin_can_confirm_down_payment(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'payment_method' => 'bank_financing',
            'down_payment_confirmed' => false,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/accounting/confirmations/{$reservation->id}/confirm");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_unauthorized_user_cannot_access_accounting_routes(): void
    {
        $normalUser = User::factory()->create();

        $response = $this->actingAs($normalUser)
            ->getJson('/api/accounting/pending-confirmations');

        $response->assertStatus(403);
    }
}
