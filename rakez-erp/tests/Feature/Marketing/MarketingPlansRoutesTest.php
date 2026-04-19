<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingProject;
use App\Models\DeveloperMarketingPlan;
use App\Models\EmployeeMarketingPlan;
use App\Models\ContractUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Canonical marketing plan routes only (see routes/api.php). Legacy /marketing/plans/* aliases are not registered.
 */
class MarketingPlansRoutesTest extends TestCase
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
    public function developer_plan_show_includes_pricing_basis_and_numeric_budget_fields(): void
    {
        $contract = Contract::factory()->create(['commission_percent' => 2.5]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 500000,
        ]);
        DeveloperMarketingPlan::create([
            'contract_id' => $contract->id,
            'average_cpm' => 10.5,
            'average_cpc' => 2.5,
            'marketing_value' => 50000,
            'expected_impressions' => 1000000,
            'expected_clicks' => 50000,
            'platforms' => [],
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/developer-plans/{$contract->id}");

        // Commission base = stored avg (500k) × 2.5% = 12,500; marketing default 10% → 1,250 (UI formula).
        // Persisted plan.marketing_value (50k) is exposed separately from calculated totals.
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contract.pricing_basis.source', 'avg_property_value_stored')
            ->assertJsonPath('data.contract.pricing_basis.avg_property_value_stored', 500000)
            ->assertJsonPath('data.calculated_contract_budget.commission_value', 12500)
            ->assertJsonPath('data.calculated_contract_budget.marketing_value', 1250)
            ->assertJsonPath('data.total_budget', 1250)
            ->assertJsonPath('data.stored_marketing_value', 50000)
            ->assertJsonPath('data.stored_plan_financials.stored_differs_from_calculated', true)
            ->assertJsonPath('data.plan.marketing_value', 50000)
            ->assertJsonPath('data.plan.marketing_value_stored', 50000)
            ->assertJsonStructure([
                'data' => [
                    'contract' => [
                        'commission_percent',
                        'pricing_basis',
                        'total_unit_price',
                        'average_unit_price',
                    ],
                    'plan' => [
                        'id',
                        'marketing_value',
                        'marketing_value_stored',
                        'platforms',
                    ],
                    'calculated_contract_budget',
                    'stored_plan_financials',
                    'total_budget',
                    'total_budget_display',
                    'stored_marketing_value',
                    'stored_marketing_value_display',
                ],
            ]);
    }

    #[Test]
    public function developer_plan_uses_sum_of_available_unit_prices_for_commission_and_marketing_formula(): void
    {
        // available: 400k + 600k = 1000k; sold 500k excluded from commission base
        $contract = Contract::factory()->create([
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 999999,
        ]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 400000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 600000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold', 'price' => 500000]);

        DeveloperMarketingPlan::create([
            'contract_id' => $contract->id,
            'average_cpm' => 10,
            'average_cpc' => 2.5,
            'marketing_percent' => 10,
            'marketing_value' => 4000,
            'expected_impressions' => 100,
            'expected_clicks' => 50,
            'platforms' => [],
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/developer-plans/{$contract->id}");

        $response->assertStatus(200)
            // Source = available-units basis
            ->assertJsonPath('data.contract.pricing_basis.source', 'unit_prices_sum_available')
            // All-units still informational
            ->assertJsonPath('data.contract.pricing_basis.total_unit_price_all_sum', 1500000)
            // commission base = 1000k (available only)
            ->assertJsonPath('data.contract.pricing_basis.total_unit_price', 1000000)
            // commission = 1000k × 2.5% = 25000; marketing = 25000 × 10% = 2500
            ->assertJsonPath('data.calculated_contract_budget.commission_value', 25000)
            ->assertJsonPath('data.calculated_contract_budget.marketing_value', 2500)
            ->assertJsonPath('data.total_budget', 2500)
            ->assertJsonPath('data.stored_marketing_value', 4000)
            // average_unit_price = available avg = (400k+600k)/2 = 500000
            ->assertJsonPath('data.contract.average_unit_price', 500000);
    }

    #[Test]
    public function employee_plans_index_by_project_works(): void
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);
        EmployeeMarketingPlan::create([
            'marketing_project_id' => $project->id,
            'user_id' => $this->marketingUser->id,
            'commission_value' => 1000,
            'marketing_value' => 5000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/employee-plans?project_id=' . $project->id);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }
}
