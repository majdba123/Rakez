<?php

namespace Tests\Feature\Accounting;

use App\Models\Commission;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class AccountingDepositTest extends TestCase
{
    use RefreshDatabase;
    use TestsWithPermissions;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoleWithPermissions('accounting', [
            'accounting.deposits.view',
            'accounting.deposits.manage',
        ]);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    #[Test]
    public function accounting_user_can_list_actionable_pending_deposits_with_resolved_domain_context(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $reservation = $this->createReservation($contract, $unit, [
            'brokerage_commission_percent' => 2.5,
            'commission_payer' => 'seller',
            'proposed_price' => 550000,
            'client_name' => 'Majd Bayer',
        ]);

        $pendingDeposit = $this->createDeposit($reservation, ['status' => 'pending']);
        $receivedDeposit = $this->createDeposit($reservation, ['status' => 'received']);
        $confirmedDeposit = $this->createDeposit($reservation, ['status' => 'confirmed']);
        $this->createDeposit($reservation, ['status' => 'refunded', 'commission_source' => 'owner']);

        $response = $this->getJson('/api/accounting/deposits/pending');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);

        $rows = collect($response->json('data'))->keyBy('deposit_id');

        $this->assertEqualsCanonicalizing(
            [$pendingDeposit->id, $receivedDeposit->id],
            $rows->keys()->all()
        );

        $pendingRow = $rows[$pendingDeposit->id];
        $this->assertSame('deposit', $pendingRow['row_entity']);
        $this->assertSame('deposit_pending_confirmation', $pendingRow['accounting_state']);
        $this->assertSame($reservation->id, $pendingRow['reservation_id']);
        $this->assertSame($contract->id, $pendingRow['project']['contract_id']);
        $this->assertSame($contract->project_name, $pendingRow['project']['name']);
        $this->assertSame($unit->id, $pendingRow['unit']['id']);
        $this->assertSame($unit->unit_number, $pendingRow['unit']['number']);
        $this->assertSame('Majd Bayer', $pendingRow['client']['name']);
        $this->assertSame(550000.0, (float) $pendingRow['pricing']['final_selling_price']);
        $this->assertSame(2.5, (float) $pendingRow['pricing']['commission_percentage']);
        $this->assertSame('owner', $pendingRow['pricing']['commission_source']);
        $this->assertSame('reservation', $pendingRow['pricing']['commission_resolution_source']);
        $this->assertTrue($pendingRow['deposit']['has_deposit']);
        $this->assertCount(1, $pendingRow['deposits']);
    }

    #[Test]
    public function pending_endpoint_excludes_refunded_and_non_confirmed_reservation_rows(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $confirmedReservation = $this->createReservation($contract, $unit, ['status' => 'confirmed']);
        $negotiationReservation = $this->createReservation($contract, $unit, [
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
            'confirmed_at' => null,
        ]);

        $includedDeposit = $this->createDeposit($confirmedReservation, ['status' => 'pending']);
        $this->createDeposit($confirmedReservation, ['status' => 'refunded', 'commission_source' => 'owner']);
        $this->createDeposit($negotiationReservation, ['status' => 'pending']);

        $response = $this->getJson('/api/accounting/deposits/pending');

        $response->assertOk()->assertJsonPath('meta.total', 1);

        $rows = collect($response->json('data'));
        $this->assertSame([$includedDeposit->id], $rows->pluck('deposit_id')->all());
        $this->assertNotContains('refunded', $rows->pluck('status')->all());
    }

    #[Test]
    public function follow_up_endpoint_represents_reservations_without_deposit_explicitly_and_safely(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext([
            'commission_percent' => null,
            'commission_from' => null,
        ]);

        $reservation = $this->createReservation($contract, $unit, [
            'client_name' => 'Awaiting Deposit Client',
            'brokerage_commission_percent' => 1.75,
            'commission_payer' => 'seller',
            'proposed_price' => 610000,
        ]);

        $response = $this->getJson('/api/accounting/deposits/follow-up');

        $response->assertOk()->assertJsonPath('meta.total', 1);

        $row = collect($response->json('data'))->firstWhere('reservation_id', $reservation->id);

        $this->assertNotNull($row);
        $this->assertSame('sales_reservation', $row['row_entity']);
        $this->assertSame('awaiting_deposit_creation', $row['accounting_state']);
        $this->assertNull($row['deposit_id']);
        $this->assertFalse($row['deposit']['has_deposit']);
        $this->assertNull($row['deposit']['id']);
        $this->assertSame(0, $row['deposit']['count']);
        $this->assertSame([], $row['deposits']);
        $this->assertSame(1.75, (float) $row['pricing']['commission_percentage']);
        $this->assertSame('owner', $row['pricing']['commission_source']);
        $this->assertSame('reservation', $row['pricing']['commission_resolution_source']);
    }

    #[Test]
    public function follow_up_endpoint_uses_real_deposit_links_and_commission_model_when_present(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext([
            'commission_percent' => 3.25,
            'commission_from' => 'owner',
        ]);

        $reservation = $this->createReservation($contract, $unit, [
            'client_name' => 'Deposit Follow Up Client',
            'brokerage_commission_percent' => 2.10,
            'commission_payer' => 'seller',
            'proposed_price' => 630000,
        ]);

        Commission::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'contract_unit_id' => $unit->id,
            'commission_percentage' => 4.50,
            'commission_source' => 'buyer',
            'final_selling_price' => 645000,
        ]);

        $olderDeposit = $this->createDeposit($reservation, [
            'status' => 'received',
            'payment_date' => now()->subDay(),
        ]);
        $latestDeposit = $this->createDeposit($reservation, [
            'status' => 'pending',
            'payment_date' => now(),
        ]);

        $response = $this->getJson('/api/accounting/deposits/follow-up');

        $response->assertOk()->assertJsonPath('meta.total', 1);

        $row = collect($response->json('data'))->firstWhere('reservation_id', $reservation->id);

        $this->assertNotNull($row);
        $this->assertSame('deposit_pending_confirmation', $row['accounting_state']);
        $this->assertSame($latestDeposit->id, $row['deposit_id']);
        $this->assertTrue($row['deposit']['has_deposit']);
        $this->assertSame(2, $row['deposit']['count']);
        $this->assertSame('pending', $row['deposit']['latest_status']);
        $this->assertSame(645000.0, (float) $row['pricing']['final_selling_price']);
        $this->assertSame(4.5, (float) $row['pricing']['commission_percentage']);
        $this->assertSame('buyer', $row['pricing']['commission_source']);
        $this->assertSame('commission', $row['pricing']['commission_resolution_source']);
        $this->assertCount(2, $row['deposits']);
        $this->assertSame([$latestDeposit->id, $olderDeposit->id], collect($row['deposits'])->pluck('deposit_id')->all());
    }

    #[Test]
    public function follow_up_endpoint_falls_back_to_contract_commission_when_no_reservation_or_commission_row_exists(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext([
            'commission_percent' => 3.5,
            'commission_from' => 'owner',
        ]);

        $reservation = $this->createReservation($contract, $unit, [
            'brokerage_commission_percent' => null,
            'commission_payer' => null,
            'proposed_price' => null,
            'client_name' => 'Contract Commission Client',
        ]);

        $response = $this->getJson('/api/accounting/deposits/follow-up');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('reservation_id', $reservation->id);

        $this->assertSame(3.5, (float) $row['pricing']['commission_percentage']);
        $this->assertSame('owner', $row['pricing']['commission_source']);
        $this->assertSame('contract', $row['pricing']['commission_resolution_source']);
        $this->assertSame((float) $unit->price, (float) $row['pricing']['final_selling_price']);
        $this->assertSame('unit', $row['pricing']['final_selling_price_resolution_source']);
    }

    #[Test]
    public function accounting_user_can_confirm_deposit_receipt(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $reservation = $this->createReservation($contract, $unit);
        $deposit = $this->createDeposit($reservation, ['status' => 'pending']);

        $response = $this->postJson("/api/accounting/deposits/{$deposit->id}/confirm");

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('deposits', [
            'id' => $deposit->id,
            'status' => 'confirmed',
            'confirmed_by' => $this->accountingUser->id,
        ]);
    }

    #[Test]
    public function accounting_user_can_process_refund_for_owner_paid_commission(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $reservation = $this->createReservation($contract, $unit);
        $deposit = $this->createDeposit($reservation, [
            'status' => 'received',
            'commission_source' => 'owner',
        ]);

        $response = $this->postJson("/api/accounting/deposits/{$deposit->id}/refund");

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('deposits', [
            'id' => $deposit->id,
            'status' => 'refunded',
        ]);
    }

    #[Test]
    public function cannot_refund_buyer_paid_commission_deposit(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $reservation = $this->createReservation($contract, $unit);
        $deposit = $this->createDeposit($reservation, [
            'status' => 'received',
            'commission_source' => 'buyer',
        ]);

        $response = $this->postJson("/api/accounting/deposits/{$deposit->id}/refund");

        $response->assertStatus(400);
    }

    #[Test]
    public function refund_with_reservation_id_returns_422_with_clear_message(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $reservationWithDeposit = $this->createReservation($contract, $unit);
        $this->createDeposit($reservationWithDeposit, [
            'status' => 'received',
            'commission_source' => 'owner',
        ]);

        $reservationWithoutDeposit = $this->createReservation($contract, $unit);

        $response = $this->postJson("/api/accounting/deposits/{$reservationWithoutDeposit->id}/refund");

        $response->assertStatus(422)->assertJsonFragment(['success' => false]);
    }

    #[Test]
    public function unified_queue_returns_both_reservations_and_deposits(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        
        // Reservation awaiting deposit
        $reservationWithoutDeposit = $this->createReservation($contract, $unit, [
            'client_name' => 'Awaiting Deposit',
        ]);
        
        // Reservation with deposits
        $reservationWithDeposits = $this->createReservation($contract, $unit, [
            'client_name' => 'Has Deposits',
        ]);
        $deposit1 = $this->createDeposit($reservationWithDeposits, ['status' => 'pending']);
        $deposit2 = $this->createDeposit($reservationWithDeposits, ['status' => 'confirmed']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');

        $response->assertOk()->assertJsonPath('meta.total', 3);

        $data = collect($response->json('data'));
        
        // Should include reservation row
        $reservationRow = $data->firstWhere('reservation_id', $reservationWithoutDeposit->id);
        $this->assertNotNull($reservationRow);
        $this->assertSame('sales_reservation', $reservationRow['row_entity']);
        $this->assertSame('awaiting_deposit_creation', $reservationRow['accounting_state']);
        $this->assertNull($reservationRow['deposit_status']);
        $this->assertFalse($reservationRow['has_deposit']);

        // Should include both deposit rows
        $depositRow1 = $data->firstWhere('deposit_id', $deposit1->id);
        $depositRow2 = $data->firstWhere('deposit_id', $deposit2->id);
        $this->assertNotNull($depositRow1);
        $this->assertNotNull($depositRow2);
        $this->assertSame('deposit', $depositRow1['row_entity']);
        $this->assertSame('deposit_pending_confirmation', $depositRow1['accounting_state']);
        $this->assertSame('pending', $depositRow1['deposit_status']);
    }

    #[Test]
    public function action_required_field_is_correct_for_all_rows(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        
        $reservationAwaitingDeposit = $this->createReservation($contract, $unit);
        
        $reservationWithPendingDeposit = $this->createReservation($contract, $unit);
        $pendingDeposit = $this->createDeposit($reservationWithPendingDeposit, ['status' => 'pending']);
        
        $reservationWithConfirmedDeposit = $this->createReservation($contract, $unit);
        $confirmedDeposit = $this->createDeposit($reservationWithConfirmedDeposit, ['status' => 'confirmed']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');

        $data = collect($response->json('data'));

        // Reservation awaiting deposit: action required
        $awaitingRow = $data->firstWhere('reservation_id', $reservationAwaitingDeposit->id);
        $this->assertTrue($awaitingRow['action_required']);

        // Pending deposit: action required
        $pendingRow = $data->firstWhere('deposit_id', $pendingDeposit->id);
        $this->assertTrue($pendingRow['action_required']);

        // Confirmed deposit: no action required
        $confirmedRow = $data->firstWhere('deposit_id', $confirmedDeposit->id);
        $this->assertFalse($confirmedRow['action_required']);
    }

    #[Test]
    public function scope_actionable_returns_only_actionable_rows(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        
        $reservationAwaitingDeposit = $this->createReservation($contract, $unit);
        
        $reservationWithPendingDeposit = $this->createReservation($contract, $unit);
        $pendingDeposit = $this->createDeposit($reservationWithPendingDeposit, ['status' => 'pending']);
        
        $reservationWithConfirmedDeposit = $this->createReservation($contract, $unit);
        $confirmedDeposit = $this->createDeposit($reservationWithConfirmedDeposit, ['status' => 'confirmed']);
        
        $reservationWithRefundedDeposit = $this->createReservation($contract, $unit);
        $this->createDeposit($reservationWithRefundedDeposit, ['status' => 'refunded', 'commission_source' => 'owner']);

        // Default scope is actionable
        $response = $this->getJson('/api/accounting/deposits/pending');

        $response->assertOk();
        $data = collect($response->json('data'));

        // Should include awaiting and pending but not confirmed/refunded
        $this->assertTrue($data->pluck('deposit_id')->contains($pendingDeposit->id));
        $this->assertTrue($data->pluck('reservation_id')->contains($reservationAwaitingDeposit->id));
        
        // Confirmed and refunded should not be in actionable scope
        $this->assertFalse($data->pluck('deposit_id')->contains($confirmedDeposit->id));
        $this->assertNull($data->firstWhere('reservation_id', $reservationWithRefundedDeposit->id));
    }

    #[Test]
    public function scope_closed_returns_only_closed_rows(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        
        $reservationWithPendingDeposit = $this->createReservation($contract, $unit);
        $this->createDeposit($reservationWithPendingDeposit, ['status' => 'pending']);
        
        $reservationWithConfirmedDeposit = $this->createReservation($contract, $unit);
        $confirmedDeposit = $this->createDeposit($reservationWithConfirmedDeposit, ['status' => 'confirmed']);
        
        $reservationWithRefundedDeposit = $this->createReservation($contract, $unit);
        $refundedDeposit = $this->createDeposit($reservationWithRefundedDeposit, ['status' => 'refunded', 'commission_source' => 'owner']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=closed');

        $response->assertOk();
        $data = collect($response->json('data'));
        $depositIds = $data->pluck('deposit_id')->all();

        // Should only include confirmed and refunded
        $this->assertContains($confirmedDeposit->id, $depositIds);
        $this->assertContains($refundedDeposit->id, $depositIds);
        $this->assertCount(2, $depositIds);
    }

    #[Test]
    public function deposit_status_field_is_null_only_for_reservations_without_deposits(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        
        $reservationAwaitingDeposit = $this->createReservation($contract, $unit);
        $reservationWithDeposit = $this->createReservation($contract, $unit);
        $deposit = $this->createDeposit($reservationWithDeposit, ['status' => 'pending']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');

        $data = collect($response->json('data'));

        $awaitingRow = $data->firstWhere('reservation_id', $reservationAwaitingDeposit->id);
        $depositRow = $data->firstWhere('deposit_id', $deposit->id);

        // Only reservation without deposit should have null deposit_status
        $this->assertNull($awaitingRow['deposit_status']);
        $this->assertSame('pending', $depositRow['deposit_status']);
    }

    protected function createProjectContext(array $contractOverrides = []): array
    {
        $contract = Contract::factory()->create(array_merge([
            'commission_percent' => 2.5,
            'commission_from' => 'owner',
        ], $contractOverrides));

        SecondPartyData::factory()->create(['contract_id' => $contract->id]);

        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'unit_number' => 'V12',
            'unit_type' => 'Apartment',
            'price' => 500000,
        ]);

        return [$contract, $unit];
    }

    protected function createReservation(Contract $contract, ContractUnit $unit, array $overrides = []): SalesReservation
    {
        return SalesReservation::factory()->create(array_merge([
            'status' => 'confirmed',
            'reservation_type' => 'confirmed_reservation',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'confirmed_at' => now(),
            'client_name' => 'Test Client',
            'proposed_price' => 520000,
        ], $overrides));
    }

    protected function createDeposit(SalesReservation $reservation, array $overrides = []): Deposit
    {
        return Deposit::factory()->create(array_merge([
            'sales_reservation_id' => $reservation->id,
            'contract_id' => $reservation->contract_id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'client_name' => $reservation->client_name,
            'payment_date' => now(),
            'commission_source' => 'owner',
        ], $overrides));
    }

    #[Test]
    public function relation_safety_all_returned_rows_have_complete_chain(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $reservation = $this->createReservation($contract, $unit);
        $deposit = $this->createDeposit($reservation, ['status' => 'pending']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');
        $response->assertOk();

        $data = collect($response->json('data'));

        // Verify every row has complete relation chain
        foreach ($data as $row) {
            $this->assertNotNull($row['contract_id'], "Row {$row['id']} missing contract_id");
            $this->assertNotNull($row['contract_unit_id'], "Row {$row['id']} missing contract_unit_id");
            $this->assertNotNull($row['client_name'], "Row {$row['id']} missing client_name");
            $this->assertNotNull($row['project_name'], "Row {$row['id']} missing project_name");
            $this->assertNotNull($row['unit_number'], "Row {$row['id']} missing unit_number");
            $this->assertNotNull($row['accounting_state'], "Row {$row['id']} missing accounting_state");
            
            // Every row must have either reservation_id OR deposit_id (or both)
            $hasReservationId = $row['reservation_id'] !== null;
            $hasDepositId = $row['deposit_id'] !== null;
            $this->assertTrue($hasReservationId || $hasDepositId, "Row {$row['id']} has neither reservation_id nor deposit_id");
        }
    }

    #[Test]
    public function deposit_amount_field_is_always_correct_from_deposit_table(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();
        $reservation = $this->createReservation($contract, $unit);

        // Create deposits with specific amounts
        $deposit1 = $this->createDeposit($reservation, [
            'status' => 'pending',
            'amount' => 5000.50,
        ]);
        $deposit2 = $this->createDeposit($reservation, [
            'status' => 'confirmed',
            'amount' => 15000.75,
        ]);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');
        $response->assertOk();

        $data = collect($response->json('data'));

        // Verify deposit amounts match exactly
        $depositRow1 = $data->firstWhere('deposit_id', $deposit1->id);
        $depositRow2 = $data->firstWhere('deposit_id', $deposit2->id);

        $this->assertSame(5000.5, (float) $depositRow1['amount']);
        $this->assertSame(15000.75, (float) $depositRow2['amount']);
        $this->assertSame(5000.5, (float) $depositRow1['deposit']['amount']);
        $this->assertSame(15000.75, (float) $depositRow2['deposit']['amount']);
    }

    #[Test]
    public function pagination_meta_is_accurate_after_accounting_state_filtering(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();

        // Create multiple reservations awaiting deposits
        for ($i = 0; $i < 5; $i++) {
            $this->createReservation($contract, $unit);
        }

        // Create deposits in various states
        $reservation = $this->createReservation($contract, $unit);
        $this->createDeposit($reservation, ['status' => 'pending']);
        $this->createDeposit($reservation, ['status' => 'confirmed']);

        // Total should be: 5 awaiting + 2 deposits = 7
        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');
        $response->assertOk();
        $this->assertSame(7, $response->json('meta.total'));

        // Filter to only awaiting deposits
        $response = $this->getJson('/api/accounting/deposits/pending?scope=all&accounting_state=awaiting_deposit_creation');
        $response->assertOk();
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertCount(5, $response->json('data'));

        // Filter to only deposit pending
        $response = $this->getJson('/api/accounting/deposits/pending?scope=all&accounting_state=deposit_pending_confirmation');
        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function unit_price_field_is_correct_from_contract_unit_table(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create(['commission_percent' => 2.5]);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);

        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'price' => 750000.00,
        ]);

        $reservation = $this->createReservation($contract, $unit);
        $deposit = $this->createDeposit($reservation, ['status' => 'pending']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');
        $response->assertOk();

        $data = collect($response->json('data'));
        $depositRow = $data->firstWhere('deposit_id', $deposit->id);

        // Unit price should match contract unit price
        $this->assertSame(750000.0, (float) $depositRow['unit_price']);
    }

    #[Test]
    public function commission_percentage_field_resolution_chain_is_correct(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext([
            'commission_percent' => 3.0,
            'commission_from' => 'owner',
        ]);

        // Test 1: Commission model overrides all
        $reservation1 = $this->createReservation($contract, $unit, [
            'brokerage_commission_percent' => 2.0,
        ]);
        Commission::factory()->create([
            'sales_reservation_id' => $reservation1->id,
            'contract_unit_id' => $unit->id,
            'commission_percentage' => 4.5,
            'commission_source' => 'buyer',
        ]);
        $this->createDeposit($reservation1, ['status' => 'pending']);

        // Test 2: Reservation field overrides contract
        $reservation2 = $this->createReservation($contract, $unit, [
            'brokerage_commission_percent' => 2.5,
        ]);
        $this->createDeposit($reservation2, ['status' => 'pending']);

        // Test 3: Contract fallback
        $reservation3 = $this->createReservation($contract, $unit, [
            'brokerage_commission_percent' => null,
        ]);
        $this->createDeposit($reservation3, ['status' => 'pending']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');
        $response->assertOk();

        $data = collect($response->json('data'));

        // Test 1: Commission model wins
        $row1 = $data->firstWhere('reservation_id', $reservation1->id);
        $this->assertSame(4.5, (float) $row1['commission_percentage']);
        $this->assertSame('commission', $row1['pricing']['commission_resolution_source']);

        // Test 2: Reservation field wins
        $row2 = $data->firstWhere('reservation_id', $reservation2->id);
        $this->assertSame(2.5, (float) $row2['commission_percentage']);
        $this->assertSame('reservation', $row2['pricing']['commission_resolution_source']);

        // Test 3: Contract field as fallback
        $row3 = $data->firstWhere('reservation_id', $reservation3->id);
        $this->assertSame(3.0, (float) $row3['commission_percentage']);
        $this->assertSame('contract', $row3['pricing']['commission_resolution_source']);
    }

    #[Test]
    public function final_selling_price_resolution_chain_is_correct(): void
    {
        Sanctum::actingAs($this->accountingUser);

        [$contract, $unit] = $this->createProjectContext();

        // Test 1: Commission model final_selling_price wins
        $reservation1 = $this->createReservation($contract, $unit, [
            'proposed_price' => 600000,
        ]);
        Commission::factory()->create([
            'sales_reservation_id' => $reservation1->id,
            'contract_unit_id' => $unit->id,
            'final_selling_price' => 700000,
        ]);
        $this->createDeposit($reservation1, ['status' => 'pending']);

        // Test 2: Reservation proposed_price wins
        $reservation2 = $this->createReservation($contract, $unit, [
            'proposed_price' => 550000,
        ]);
        $this->createDeposit($reservation2, ['status' => 'pending']);

        // Test 3: Unit price fallback
        $reservation3 = $this->createReservation($contract, $unit, [
            'proposed_price' => null,
        ]);
        $this->createDeposit($reservation3, ['status' => 'pending']);

        $response = $this->getJson('/api/accounting/deposits/pending?scope=all');
        $response->assertOk();

        $data = collect($response->json('data'));

        // Test 1: Commission model final_selling_price wins
        $row1 = $data->firstWhere('reservation_id', $reservation1->id);
        $this->assertSame(700000.0, (float) $row1['final_selling_price']);
        $this->assertSame('commission', $row1['pricing']['final_selling_price_resolution_source']);

        // Test 2: Reservation proposed_price wins
        $row2 = $data->firstWhere('reservation_id', $reservation2->id);
        $this->assertSame(550000.0, (float) $row2['final_selling_price']);
        $this->assertSame('reservation', $row2['pricing']['final_selling_price_resolution_source']);

        // Test 3: Unit price fallback
        $row3 = $data->firstWhere('reservation_id', $reservation3->id);
        $this->assertSame((float) $unit->price, (float) $row3['final_selling_price']);
        $this->assertSame('unit', $row3['pricing']['final_selling_price_resolution_source']);
    }
}
