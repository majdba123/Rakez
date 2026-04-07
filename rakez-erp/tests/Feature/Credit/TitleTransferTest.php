<?php

namespace Tests\Feature\Credit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contract;
use App\Models\SecondPartyData;
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

        Permission::firstOrCreate(['name' => 'credit.bookings.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'credit.title_transfer.manage', 'guard_name' => 'web']);

        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.bookings.view', 'credit.title_transfer.manage']);

        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');
    }

    /**
     * Helper: create a contract with N units under the same SecondPartyData.
     *
     * @return array{contract: Contract, units: ContractUnit[]}
     */
    private function createContractWithUnits(int $count = 3, string $unitStatus = 'reserved'): array
    {
        $contract = Contract::factory()->create();
        $spd = SecondPartyData::factory()->create(['contract_id' => $contract->id]);

        $units = [];
        for ($i = 0; $i < $count; $i++) {
            $units[] = ContractUnit::factory()->create([
                'contract_id' => $spd->contract_id,
                'status' => $unitStatus,
            ]);
        }

        return ['contract' => $contract, 'units' => $units];
    }

    // ──────────────────────────────────────────────
    //  Initialize
    // ──────────────────────────────────────────────

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

    public function test_cannot_initialize_title_transfer_while_financing_stage_6_in_progress(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        CreditFinancingTracker::factory()
            ->atStage6InProgress()
            ->create(['sales_reservation_id' => $reservation->id]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/title-transfer");

        $response->assertStatus(400);
    }

    public function test_cannot_initialize_title_transfer_for_unconfirmed_reservation(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'under_negotiation',
            'purchase_mechanism' => 'cash',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/title-transfer");

        $response->assertStatus(400);
    }

    public function test_cannot_initialize_duplicate_title_transfer(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'purchase_mechanism' => 'cash',
        ]);

        TitleTransfer::factory()->inPreparation()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/bookings/{$reservation->id}/title-transfer");

        $response->assertStatus(400);
    }

    // ──────────────────────────────────────────────
    //  Schedule
    // ──────────────────────────────────────────────

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

    public function test_cannot_schedule_already_completed_transfer(): void
    {
        $transfer = TitleTransfer::factory()->completed()->create();

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/title-transfer/{$transfer->id}/schedule", [
                'scheduled_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(400);
    }

    // ──────────────────────────────────────────────
    //  Unschedule
    // ──────────────────────────────────────────────

    public function test_can_unschedule_scheduled_transfer(): void
    {
        $transfer = TitleTransfer::factory()->scheduled()->create();

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/title-transfer/{$transfer->id}/unschedule");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', 'preparation');

        $this->assertDatabaseHas('title_transfers', [
            'id' => $transfer->id,
            'status' => 'preparation',
            'scheduled_date' => null,
        ]);
    }

    public function test_cannot_unschedule_completed_transfer(): void
    {
        $transfer = TitleTransfer::factory()->completed()->create();

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/title-transfer/{$transfer->id}/unschedule");

        $response->assertStatus(400);
    }

    public function test_cannot_unschedule_preparation_transfer(): void
    {
        $transfer = TitleTransfer::factory()->inPreparation()->create();

        $response = $this->actingAs($this->creditUser)
            ->patchJson("/api/credit/title-transfer/{$transfer->id}/unschedule");

        $response->assertStatus(400);
    }

    // ──────────────────────────────────────────────
    //  Complete – unit status
    // ──────────────────────────────────────────────

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

    public function test_complete_transfer_marks_unit_sold_when_unit_exists(): void
    {
        ['contract' => $contract, 'units' => $units] = $this->createContractWithUnits(3);

        $targetUnit = $units[0];
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $targetUnit->id,
        ]);

        $transfer = TitleTransfer::factory()->scheduled()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/title-transfer/{$transfer->id}/complete");

        $response->assertStatus(200);

        $this->assertDatabaseHas('contract_units', [
            'id' => $targetUnit->id,
            'status' => 'sold',
        ]);
    }

    public function test_cannot_complete_already_completed_transfer(): void
    {
        $transfer = TitleTransfer::factory()->completed()->create();

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/title-transfer/{$transfer->id}/complete");

        $response->assertStatus(400);
    }

    // ──────────────────────────────────────────────
    //  Complete – contract is_closed
    // ──────────────────────────────────────────────

    public function test_contract_is_closed_when_all_units_become_sold(): void
    {
        ['contract' => $contract, 'units' => $units] = $this->createContractWithUnits(2);

        // First unit already sold
        $units[0]->update(['status' => 'sold']);

        // Second unit will be sold via title transfer
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $units[1]->id,
        ]);

        $transfer = TitleTransfer::factory()->scheduled()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/title-transfer/{$transfer->id}/complete");

        $response->assertStatus(200);

        $this->assertDatabaseHas('contract_units', [
            'id' => $units[1]->id,
            'status' => 'sold',
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'is_closed' => true,
        ]);
    }

    public function test_contract_not_closed_when_some_units_still_unsold(): void
    {
        ['contract' => $contract, 'units' => $units] = $this->createContractWithUnits(3);

        // Only the first unit will be sold; two remain reserved
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $units[0]->id,
        ]);

        $transfer = TitleTransfer::factory()->scheduled()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/title-transfer/{$transfer->id}/complete");

        $response->assertStatus(200);

        $this->assertDatabaseHas('contract_units', [
            'id' => $units[0]->id,
            'status' => 'sold',
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'is_closed' => false,
        ]);
    }

    public function test_contract_closed_when_single_unit_project_sold(): void
    {
        ['contract' => $contract, 'units' => $units] = $this->createContractWithUnits(1);

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $units[0]->id,
        ]);

        $transfer = TitleTransfer::factory()->scheduled()->create([
            'sales_reservation_id' => $reservation->id,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->postJson("/api/credit/title-transfer/{$transfer->id}/complete");

        $response->assertStatus(200);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'is_closed' => true,
        ]);
    }

    public function test_contract_closes_progressively_as_units_sell(): void
    {
        ['contract' => $contract, 'units' => $units] = $this->createContractWithUnits(3);

        // Sell unit 1 of 3
        $res1 = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $units[0]->id,
        ]);
        $t1 = TitleTransfer::factory()->scheduled()->create(['sales_reservation_id' => $res1->id]);
        $this->actingAs($this->creditUser)->postJson("/api/credit/title-transfer/{$t1->id}/complete");

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'is_closed' => false]);

        // Sell unit 2 of 3
        $res2 = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $units[1]->id,
        ]);
        $t2 = TitleTransfer::factory()->scheduled()->create(['sales_reservation_id' => $res2->id]);
        $this->actingAs($this->creditUser)->postJson("/api/credit/title-transfer/{$t2->id}/complete");

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'is_closed' => false]);

        // Sell unit 3 of 3 — contract should close
        $res3 = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $units[2]->id,
        ]);
        $t3 = TitleTransfer::factory()->scheduled()->create(['sales_reservation_id' => $res3->id]);
        $this->actingAs($this->creditUser)->postJson("/api/credit/title-transfer/{$t3->id}/complete");

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'is_closed' => true]);
    }

    // ──────────────────────────────────────────────
    //  Sold projects listing
    // ──────────────────────────────────────────────

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
            ->assertJsonPath('meta.pagination.total', 2);
    }

    // ──────────────────────────────────────────────
    //  Pending transfers listing
    // ──────────────────────────────────────────────

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
