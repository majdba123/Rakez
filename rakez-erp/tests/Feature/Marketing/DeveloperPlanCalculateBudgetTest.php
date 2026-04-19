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
    public function calculate_budget_uses_sum_of_available_unit_prices_only_when_no_override(): void
    {
        // Business rule: commission base = available units only
        // available: 400k + 600k = 1000k; sold: 500k (excluded)
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
            // Source must reflect available-units basis, not all-units
            ->assertJsonPath('data.pricing_basis.source', 'unit_prices_sum_available')
            // Commission base = available units sum (1000k), not all-units (1500k)
            ->assertJsonPath('data.pricing_basis.total_unit_price', 1000000)
            ->assertJsonPath('data.pricing_basis.total_unit_price_available_sum', 1000000)
            // All-units figures still present as informational
            ->assertJsonPath('data.pricing_basis.total_unit_price_all_sum', 1500000)
            // commission = 1000k × 2.5% = 25000 (not 37500)
            ->assertJsonPath('data.commission_value', 25000)
            // marketing = 25000 × 10% = 2500 (not 3750)
            ->assertJsonPath('data.marketing_value', 2500)
            ->assertJsonPath('data.calculated_contract_budget.commission_value', 25000)
            ->assertJsonPath('data.calculated_contract_budget.marketing_value', 2500)
            // calculated_contract_budget surfaces available-units fields
            ->assertJsonPath('data.calculated_contract_budget.total_unit_price_available_sum', 1000000)
            ->assertJsonPath('data.calculated_contract_budget.available_units_count', 2);
    }

    #[Test]
    public function calculate_budget_excludes_pending_and_sold_units_from_commission_base(): void
    {
        // Mixed project: available, pending, sold, reserved — only available must be used
        $contract = Contract::factory()->create(['commission_percent' => 2.5]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 30,
        ]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 500000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 700000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending',   'price' => 900000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold',      'price' => 1200000]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/developer-plans/calculate-budget', [
                'contract_id' => $contract->id,
                'marketing_percent' => 10,
            ]);

        $response->assertStatus(200);
        // available sum = 500k + 700k = 1200k
        // commission = 1200k × 2.5% = 30000
        // marketing  = 30000 × 10% = 3000
        $response->assertJsonPath('data.pricing_basis.total_unit_price', 1200000)
            ->assertJsonPath('data.commission_value', 30000)
            ->assertJsonPath('data.marketing_value', 3000);
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
