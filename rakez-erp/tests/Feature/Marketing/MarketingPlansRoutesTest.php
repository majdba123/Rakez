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

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contract.pricing_basis.source', 'avg_property_value_stored')
            ->assertJsonPath('data.contract.pricing_basis.avg_property_value_stored', 500000)
            ->assertJsonPath('data.total_budget', 50000)
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
                        'platforms',
                    ],
                    'total_budget',
                    'total_budget_display',
                ],
            ]);
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
