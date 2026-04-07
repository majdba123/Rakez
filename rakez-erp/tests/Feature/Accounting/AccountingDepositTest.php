<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;
use App\Models\User;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AccountingDepositTest extends TestCase
{
    use RefreshDatabase, TestsWithPermissions;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create accounting role with required permissions
        $this->createRoleWithPermissions('accounting', [
            'accounting.deposits.view',
            'accounting.deposits.manage',
        ]);
        
        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /** @test */
    public function accounting_user_can_list_pending_deposits()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
        $deposit = Deposit::factory()->create([
            'status' => 'pending',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->getJson('/api/accounting/deposits/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ])
            ->assertJsonPath('data.0.deposit_id', $deposit->id)
            ->assertJsonPath('data.0.reservation_id', $deposit->sales_reservation_id)
            ->assertJsonPath('data.0.row_entity', 'deposit');
    }

    /** @test */
    public function accounting_user_can_confirm_deposit_receipt()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
        $deposit = Deposit::factory()->create([
            'status' => 'pending',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->postJson("/api/accounting/deposits/{$deposit->id}/confirm");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('deposits', [
            'id' => $deposit->id,
            'status' => 'confirmed',
            'confirmed_by' => $this->accountingUser->id,
        ]);
    }

    /** @test */
    public function accounting_user_can_process_refund_for_owner_paid_commission()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
        $deposit = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'owner',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->postJson("/api/accounting/deposits/{$deposit->id}/refund");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('deposits', [
            'id' => $deposit->id,
            'status' => 'refunded',
        ]);
    }

    /** @test */
    public function cannot_refund_buyer_paid_commission_deposit()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
        $deposit = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'buyer',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->postJson("/api/accounting/deposits/{$deposit->id}/refund");

        $response->assertStatus(400);
    }

    /** @test */
    public function accounting_user_can_get_follow_up_list()
    {
        Sanctum::actingAs($this->accountingUser);

        $response = $this->getJson('/api/accounting/deposits/follow-up');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    /** @test */
    public function follow_up_rows_expose_reservation_id_and_nested_deposit_ids(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'confirmed_at' => now(),
        ]);
        $deposit = Deposit::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'status' => 'received',
        ]);

        $response = $this->getJson('/api/accounting/deposits/follow-up');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.reservation_id', $reservation->id)
            ->assertJsonPath('data.0.row_entity', 'sales_reservation')
            ->assertJsonPath('data.0.deposit_id', null)
            ->assertJsonPath('data.0.deposits.0.deposit_id', $deposit->id);
    }

    /** @test */
    public function refund_with_reservation_id_returns_422_with_clear_message(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
        $reservationWithDeposit = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);
        Deposit::factory()->create([
            'sales_reservation_id' => $reservationWithDeposit->id,
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'status' => 'received',
            'commission_source' => 'owner',
        ]);
        // Second reservation with no deposit row sharing this id (avoid deposit PK = reservation PK collision).
        $reservationWithoutDeposit = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->postJson("/api/accounting/deposits/{$reservationWithoutDeposit->id}/refund");

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }
}
