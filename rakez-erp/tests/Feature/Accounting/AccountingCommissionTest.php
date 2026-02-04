<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AccountingCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /** @test */
    public function accounting_user_can_list_sold_units()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->getJson('/api/accounting/sold-units');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    /** @test */
    public function accounting_user_can_view_single_sold_unit()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->getJson("/api/accounting/sold-units/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function accounting_user_can_approve_commission_distribution()
    {
        Sanctum::actingAs($this->accountingUser);

        $commission = Commission::factory()->create(['status' => 'pending']);
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/accounting/commissions/{$commission->id}/distributions/{$distribution->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('commission_distributions', [
            'id' => $distribution->id,
            'status' => 'approved',
            'approved_by' => $this->accountingUser->id,
        ]);
    }

    /** @test */
    public function accounting_user_can_reject_commission_distribution()
    {
        Sanctum::actingAs($this->accountingUser);

        $commission = Commission::factory()->create(['status' => 'pending']);
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/accounting/commissions/{$commission->id}/distributions/{$distribution->id}/reject", [
            'notes' => 'Invalid percentage',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('commission_distributions', [
            'id' => $distribution->id,
            'status' => 'rejected',
        ]);
    }

    /** @test */
    public function accounting_user_can_get_commission_summary()
    {
        Sanctum::actingAs($this->accountingUser);

        $commission = Commission::factory()->create();

        $response = $this->getJson("/api/accounting/commissions/{$commission->id}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'commission_id',
                    'final_selling_price',
                    'commission_percentage',
                    'total_before_tax',
                    'vat',
                    'marketing_expenses',
                    'bank_fees',
                    'net_amount',
                    'distributions',
                ],
            ]);
    }
}
