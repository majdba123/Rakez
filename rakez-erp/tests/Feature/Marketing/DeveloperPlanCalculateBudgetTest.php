<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Canonical budget math lives in POST /marketing/developer-plans/calculate-budget only.
 */
class DeveloperPlanCalculateBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->assignRole('marketing');
        $this->marketingUser->givePermissionTo('marketing.plans.create');
    }

    #[Test]
    public function calculate_budget_applies_override_and_default_marketing_percent(): void
    {
        $contract = Contract::factory()->create([
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 30,
            'agreement_duration_months' => 1,
            'avg_property_value' => 1000000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/developer-plans/calculate-budget', [
                'contract_id' => $contract->id,
                'marketing_percent' => 10,
                'unit_price' => 1000000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.commission_value', 25000)
            ->assertJsonPath('data.marketing_value', 2500)
            ->assertJsonPath('data.pricing_basis.source', 'total_unit_price_override')
            ->assertJsonPath('data.pricing_basis.total_unit_price', 1000000)
            ->assertJsonStructure([
                'data' => [
                    'expected_impressions',
                    'expected_clicks',
                    'average_cpm',
                    'average_cpc',
                ],
            ]);
    }

    #[Test]
    public function calculate_budget_uses_sum_of_all_unit_prices_when_no_override(): void
    {
        $contract = Contract::factory()->create([
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 30,
            'agreement_duration_months' => 1,
            'avg_property_value' => 999999,
        ]);
        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'price' => 400000,
        ]);
        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'price' => 600000,
        ]);
        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'sold',
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/developer-plans/calculate-budget', [
                'contract_id' => $contract->id,
                'marketing_percent' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.pricing_basis.source', 'unit_prices_sum_all')
            ->assertJsonPath('data.pricing_basis.total_unit_price', 1500000)
            ->assertJsonPath('data.pricing_basis.total_unit_price_available_sum', 1000000)
            ->assertJsonPath('data.commission_value', 37500)
            ->assertJsonPath('data.marketing_value', 3750)
            ->assertJsonPath('data.calculated_contract_budget.commission_value', 37500)
            ->assertJsonPath('data.calculated_contract_budget.marketing_value', 3750);
    }

    #[Test]
    public function calculate_budget_uses_contract_duration_months_for_monthly_budget_when_available(): void
    {
        $contract = Contract::factory()->create([
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 60,
            'agreement_duration_months' => 2,
            'avg_property_value' => 1000000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/developer-plans/calculate-budget', [
                'contract_id' => $contract->id,
                'marketing_percent' => 10,
                'unit_price' => 1000000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.monthly_budget', 1250);
    }
}
