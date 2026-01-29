<?php

namespace Tests\Feature\Credit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\CreditFinancingTracker;
use App\Models\TitleTransfer;
use App\Models\ContractUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TitleTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $creditUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::firstOrCreate(['name' => 'credit.bookings.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'credit.title_transfer.manage', 'guard_name' => 'web']);

        // Create role
        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.bookings.view', 'credit.title_transfer.manage']);

        // Create user
        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');
    }

    public function test_can_initialize_title_transfer_for_cash_purchase(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'cash',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/title-transfer");

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('title_transfers', [
            'sales_reservation_id' => $reservation->id,
            'status' => 'preparation',
        ]);
    }

    public function test_can_initialize_title_transfer_after_financing_completed(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        CreditFinancingTracker::factory()->completed()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/title-transfer");

        $response->assertStatus(201);
    }

    public function test_cannot_initialize_title_transfer_before_financing_completed(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'overall_status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/title-transfer");

        $response->assertStatus(400);
    }

    public function test_can_schedule_title_transfer(): void
    {
        $transfer = TitleTransfer::factory()->inPreparation()->create();

        $scheduledDate = now()->addDays(7)->format('Y-m-d');

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/title-transfer/{$transfer->id}/schedule", [
                'scheduled_date' => $scheduledDate,
                'notes' => 'موعد نقل الملكية',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_cannot_schedule_with_past_date(): void
    {
        $transfer = TitleTransfer::factory()->inPreparation()->create();

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/title-transfer/{$transfer->id}/schedule", [
                'scheduled_date' => now()->subDays(5)->format('Y-m-d'),
            ]);

        $response->assertStatus(422);
    }

    public function test_can_complete_title_transfer(): void
    {
        $unit = ContractUnit::factory()->create(['status' => 'reserved']);
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_unit_id' => $unit->id,
        ]);

        $transfer = TitleTransfer::factory()->scheduled()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/title-transfer/{$transfer->id}/complete");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('sales_reservations', [
            'id' => $reservation->id,
            'credit_status' => 'sold',
        ]);

        $this->assertDatabaseHas('contract_units', [
            'id' => $unit->id,
            'status' => 'sold',
        ]);
    }

    public function test_can_list_sold_projects(): void
    {
        $reservation1 = SalesReservation::factory()->create([
            'credit_status' => 'sold',
        ]);

        TitleTransfer::factory()->completed()->create([
            'sales_reservation_id' => $reservation1->id,
        ]);

        $reservation2 = SalesReservation::factory()->create([
            'credit_status' => 'sold',
        ]);

        TitleTransfer::factory()->completed()->create([
            'sales_reservation_id' => $reservation2->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/sold-projects');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_list_pending_title_transfers(): void
    {
        TitleTransfer::factory()->count(2)->inPreparation()->create();
        TitleTransfer::factory()->scheduled()->create();
        TitleTransfer::factory()->completed()->create();

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/title-transfers/pending');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3); // 2 preparation + 1 scheduled
    }
}

