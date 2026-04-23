<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;
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
    use RefreshDatabase, TestsWithPermissions;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create accounting role with required permissions
        $this->createRoleWithPermissions('accounting', [
            'accounting.sold-units.view',
            'accounting.sold-units.manage',
            'accounting.commissions.approve',
            'accounting.commissions.create',
        ]);
        
        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /** @test */
    public function accounting_user_can_list_sold_units()
    {
        Sanctum::actingAs($this->accountingUser);

        $contract = Contract::factory()->create();
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
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
        $unit = ContractUnit::factory()->create(['contract_id' => $secondPartyData->contract_id]);
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $response = $this->getJson("/api/accounting/sold-units/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['available_marketers']]);
    }

    /** @test */
    public function accounting_user_can_list_marketers_for_dropdown()
    {
        Sanctum::actingAs($this->accountingUser);

        User::factory()->create(['type' => 'sales', 'is_active' => true, 'name' => 'Sales User']);
        User::factory()->create(['type' => 'marketing', 'is_active' => true, 'name' => 'Marketing User']);

        $response = $this->getJson('/api/accounting/marketers');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
            ]);
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

    /** @test */
    public function commission_distributions_can_sum_below_100_with_remainder_metadata(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $payee = User::factory()->create(['type' => 'sales', 'is_active' => true]);
        $commission = Commission::factory()->create([
            'status' => 'pending',
            'net_amount' => 10000,
        ]);

        $response = $this->putJson("/api/accounting/commissions/{$commission->id}/distributions", [
            'distributions' => [
                [
                    'type' => 'closing',
                    'percentage' => 40,
                    'user_id' => $payee->id,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.remaining_percentage', 60)
            ->assertJsonPath('data.remaining_amount', 6000)
            ->assertJsonPath('data.total_distributed_percentage', 40);

        $commission->refresh();
        $this->assertCount(1, $commission->distributions);
        $this->assertEquals(4000.0, (float) $commission->distributions->first()->amount);
    }

    /** @test */
    public function commission_distributions_empty_array_clears_rows_and_full_remainder_to_company(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $commission = Commission::factory()->create([
            'status' => 'pending',
            'net_amount' => 5000,
        ]);
        CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'percentage' => 50,
            'amount' => 2500,
            'status' => 'pending',
        ]);

        $response = $this->putJson("/api/accounting/commissions/{$commission->id}/distributions", [
            'distributions' => [],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.remaining_percentage', 100)
            ->assertJsonPath('data.remaining_amount', 5000);

        $this->assertCount(0, $commission->fresh()->distributions);
    }

    /** @test */
    public function commission_distributions_reject_total_over_100(): void
    {
        Sanctum::actingAs($this->accountingUser);

        $u1 = User::factory()->create(['type' => 'sales', 'is_active' => true]);
        $u2 = User::factory()->create(['type' => 'sales', 'is_active' => true]);
        $commission = Commission::factory()->create([
            'status' => 'pending',
            'net_amount' => 10000,
        ]);

        $response = $this->putJson("/api/accounting/commissions/{$commission->id}/distributions", [
            'distributions' => [
                ['type' => 'closing', 'percentage' => 60, 'user_id' => $u1->id],
                ['type' => 'persuasion', 'percentage' => 50, 'user_id' => $u2->id],
            ],
        ]);

        $response->assertStatus(400);
    }
}
