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
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);
        Deposit::factory()->create([
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
            ]);
    }

    /** @test */
    public function accounting_user_can_confirm_deposit_receipt()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);
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
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);
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
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);
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
}
