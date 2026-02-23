<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;
use App\Models\DeveloperMarketingPlan;
use App\Models\MarketingBudgetDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MarketingBudgetDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;
    private User $adminUser;
    private User $unauthorizedUser;
    private MarketingProject $marketingProject;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // Create users
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->assignRole('marketing');

        $this->adminUser = User::factory()->create(['type' => 'admin']);
        $this->adminUser->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create(['type' => 'sales']);
        $this->unauthorizedUser->assignRole('sales');

        // Create contract and project
        $this->contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $this->contract->id,
            'commission_percent' => 2.5,
            'avg_property_value' => 2000
        ]);
        $this->marketingProject = MarketingProject::create([
            'contract_id' => $this->contract->id
        ]);
    }

    #[Test]
    public function it_can_create_budget_distribution_for_employee_plan()
    {
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'commission_value' => 20000,
            'marketing_value' => 35000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/budget-distributions', [
                'marketing_project_id' => $this->marketingProject->id,
                'plan_type' => 'employee',
                'employee_marketing_plan_id' => $employeePlan->id,
                'total_budget' => 35000,
                'platform_distribution' => [
                    'TikTok' => 20,
                    'Meta' => 30,
                    'Snapchat' => 20,
                    'Google' => 10,
                    'X' => 20,
                ],
                'platform_objectives' => [
                    'Meta' => [
                        'impression_percent' => 20,
                        'lead_percent' => 50,
                        'direct_contact_percent' => 30,
                    ],
                    'TikTok' => [
                        'impression_percent' => 20,
                        'lead_percent' => 50,
                        'direct_contact_percent' => 30,
                    ],
                ],
                'platform_costs' => [
                    'Meta' => [
                        'cpl' => 25,
                        'direct_contact_cost' => 35,
                    ],
                    'TikTok' => [
                        'cpl' => 25,
                        'direct_contact_cost' => 35,
                    ],
                ],
                'cost_source' => [
                    'Meta' => 'manual',
                    'TikTok' => 'manual',
                ],
                'conversion_rate' => 3,
                'average_booking_value' => 2000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'marketing_project_id',
                    'plan_type',
                    'total_budget',
                    'platform_distribution',
                    'platform_objectives',
                    'calculated_results',
                ]
            ]);

        $this->assertDatabaseHas('marketing_budget_distributions', [
            'marketing_project_id' => $this->marketingProject->id,
            'plan_type' => 'employee',
            'total_budget' => 35000,
        ]);
    }

    #[Test]
    public function it_can_create_budget_distribution_for_developer_plan()
    {
        $developerPlan = DeveloperMarketingPlan::create([
            'contract_id' => $this->contract->id,
            'marketing_value' => 50000,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/marketing/budget-distributions', [
                'marketing_project_id' => $this->marketingProject->id,
                'plan_type' => 'developer',
                'developer_marketing_plan_id' => $developerPlan->id,
                'total_budget' => 50000,
                'platform_distribution' => [
                    'TikTok' => 20,
                    'Meta' => 30,
                    'Snapchat' => 20,
                    'Google' => 10,
                    'X' => 20,
                ],
                'platform_objectives' => [
                    'Meta' => [
                        'impression_percent' => 20,
                        'lead_percent' => 50,
                        'direct_contact_percent' => 30,
                    ],
                ],
                'platform_costs' => [
                    'Meta' => [
                        'cpl' => 25,
                        'direct_contact_cost' => 35,
                    ],
                ],
                'cost_source' => [
                    'Meta' => 'manual',
                ],
                'conversion_rate' => 3,
                'average_booking_value' => 2000,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('marketing_budget_distributions', [
            'marketing_project_id' => $this->marketingProject->id,
            'plan_type' => 'developer',
        ]);
    }

    #[Test]
    public function it_validates_platform_distribution_sums_to_100_percent()
    {
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/budget-distributions', [
                'marketing_project_id' => $this->marketingProject->id,
                'plan_type' => 'employee',
                'total_budget' => 35000,
                'platform_distribution' => [
                    'TikTok' => 20,
                    'Meta' => 30,
                    'Snapchat' => 20,
                    'Google' => 10,
                    'X' => 15, // Total = 95, should fail
                ],
                'platform_objectives' => [
                    'Meta' => [
                        'impression_percent' => 20,
                        'lead_percent' => 50,
                        'direct_contact_percent' => 30,
                    ],
                ],
                'platform_costs' => [
                    'Meta' => [
                        'cpl' => 25,
                        'direct_contact_cost' => 35,
                    ],
                ],
                'conversion_rate' => 3,
                'average_booking_value' => 2000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform_distribution']);
    }

    #[Test]
    public function it_validates_platform_objectives_sums_to_100_percent()
    {
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/budget-distributions', [
                'marketing_project_id' => $this->marketingProject->id,
                'plan_type' => 'employee',
                'total_budget' => 35000,
                'platform_distribution' => [
                    'TikTok' => 20,
                    'Meta' => 30,
                    'Snapchat' => 20,
                    'Google' => 10,
                    'X' => 20,
                ],
                'platform_objectives' => [
                    'Meta' => [
                        'impression_percent' => 20,
                        'lead_percent' => 50,
                        'direct_contact_percent' => 25, // Total = 95, should fail
                    ],
                ],
                'platform_costs' => [
                    'Meta' => [
                        'cpl' => 25,
                        'direct_contact_cost' => 35,
                    ],
                ],
                'conversion_rate' => 3,
                'average_booking_value' => 2000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform_objectives']);
    }

    #[Test]
    public function it_calculates_budget_distribution_correctly()
    {
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'marketing_value' => 35000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/budget-distributions', [
                'marketing_project_id' => $this->marketingProject->id,
                'plan_type' => 'employee',
                'employee_marketing_plan_id' => $employeePlan->id,
                'total_budget' => 35000,
                'platform_distribution' => [
                    'Meta' => 100, // 100% to Meta for easier calculation
                ],
                'platform_objectives' => [
                    'Meta' => [
                        'impression_percent' => 20,
                        'lead_percent' => 50,
                        'direct_contact_percent' => 30,
                    ],
                ],
                'platform_costs' => [
                    'Meta' => [
                        'cpl' => 25,
                        'direct_contact_cost' => 35,
                    ],
                ],
                'cost_source' => [
                    'Meta' => 'manual',
                ],
                'conversion_rate' => 3,
                'average_booking_value' => 2000,
            ]);

        $response->assertStatus(201);
        $distribution = MarketingBudgetDistribution::where('marketing_project_id', $this->marketingProject->id)->first();
        
        $this->assertNotNull($distribution->calculated_results);
        
        $results = $distribution->calculated_results;
        
        // Meta budget = 35000 * 100% = 35000
        $this->assertEquals(35000, $results['platform_budgets']['Meta']);
        
        // Lead budget = 35000 * 50% = 17500
        $this->assertEquals(17500, $results['objective_budgets']['Meta']['lead']);
        
        // Direct contact budget = 35000 * 30% = 10500
        $this->assertEquals(10500, $results['objective_budgets']['Meta']['direct_contact']);
        
        // Leads count = 17500 / 25 = 700
        $this->assertEquals(700, $results['leads_count']['Meta']);
        
        // Direct contacts count = 10500 / 35 = 300
        $this->assertEquals(300, $results['direct_contacts_count']['Meta']);
        
        // Total opportunities = 700 + 300 = 1000
        $this->assertEquals(1000, $results['total_opportunities']);
        
        // Expected bookings = 1000 * 3% = 30
        $this->assertEquals(30, $results['expected_bookings']);
        
        // Expected revenue = 30 * 2000 = 60000
        $this->assertEquals(60000, $results['expected_revenue']);
        
        // Cost per booking = 35000 / 30 = 1166.67
        $this->assertEquals(1166.67, round($results['cost_per_booking'], 2));
    }

    #[Test]
    public function it_can_retrieve_budget_distribution_by_project_id()
    {
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'marketing_value' => 35000,
        ]);

        $distribution = MarketingBudgetDistribution::create([
            'marketing_project_id' => $this->marketingProject->id,
            'plan_type' => 'employee',
            'employee_marketing_plan_id' => $employeePlan->id,
            'total_budget' => 35000,
            'platform_distribution' => ['Meta' => 100],
            'platform_objectives' => ['Meta' => ['impression_percent' => 20, 'lead_percent' => 50, 'direct_contact_percent' => 30]],
            'platform_costs' => ['Meta' => ['cpl' => 25, 'direct_contact_cost' => 35]],
            'conversion_rate' => 3,
            'average_booking_value' => 2000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/budget-distributions/{$this->marketingProject->id}?plan_type=employee");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $distribution->id);
    }

    #[Test]
    public function it_can_recalculate_budget_distribution()
    {
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'marketing_value' => 35000,
        ]);

        $distribution = MarketingBudgetDistribution::create([
            'marketing_project_id' => $this->marketingProject->id,
            'plan_type' => 'employee',
            'employee_marketing_plan_id' => $employeePlan->id,
            'total_budget' => 35000,
            'platform_distribution' => ['Meta' => 100],
            'platform_objectives' => ['Meta' => ['impression_percent' => 20, 'lead_percent' => 50, 'direct_contact_percent' => 30]],
            'platform_costs' => ['Meta' => ['cpl' => 25, 'direct_contact_cost' => 35]],
            'conversion_rate' => 3,
            'average_booking_value' => 2000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson("/api/marketing/budget-distributions/{$distribution->id}/calculate");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'distribution',
                    'results',
                ]
            ]);
    }

    #[Test]
    public function it_can_retrieve_calculated_results()
    {
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'marketing_value' => 35000,
        ]);

        $distribution = MarketingBudgetDistribution::create([
            'marketing_project_id' => $this->marketingProject->id,
            'plan_type' => 'employee',
            'employee_marketing_plan_id' => $employeePlan->id,
            'total_budget' => 35000,
            'platform_distribution' => ['Meta' => 100],
            'platform_objectives' => ['Meta' => ['impression_percent' => 20, 'lead_percent' => 50, 'direct_contact_percent' => 30]],
            'platform_costs' => ['Meta' => ['cpl' => 25, 'direct_contact_cost' => 35]],
            'conversion_rate' => 3,
            'average_booking_value' => 2000,
            'calculated_results' => ['test' => 'data'],
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/budget-distributions/{$distribution->id}/results");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'distribution_id',
                    'marketing_project_id',
                    'plan_type',
                    'total_budget',
                    'results',
                ]
            ]);
    }

    #[Test]
    public function unauthorized_user_cannot_access_budget_distributions()
    {
        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->postJson('/api/marketing/budget-distributions', [
                'marketing_project_id' => $this->marketingProject->id,
                'plan_type' => 'employee',
                'total_budget' => 35000,
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_zero_division_gracefully()
    {
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'marketing_value' => 35000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/budget-distributions', [
                'marketing_project_id' => $this->marketingProject->id,
                'plan_type' => 'employee',
                'employee_marketing_plan_id' => $employeePlan->id,
                'total_budget' => 35000,
                'platform_distribution' => [
                    'Meta' => 100,
                ],
                'platform_objectives' => [
                    'Meta' => [
                        'impression_percent' => 20,
                        'lead_percent' => 50,
                        'direct_contact_percent' => 30,
                    ],
                ],
                'platform_costs' => [
                    'Meta' => [
                        'cpl' => 0, // Zero CPL
                        'direct_contact_cost' => 0, // Zero cost
                    ],
                ],
                'conversion_rate' => 0, // Zero conversion rate
                'average_booking_value' => 2000,
            ]);

        // Should still create but with zero counts
        $response->assertStatus(201);
        $distribution = MarketingBudgetDistribution::where('marketing_project_id', $this->marketingProject->id)->first();
        $results = $distribution->calculated_results;
        
        $this->assertEquals(0, $results['leads_count']['Meta']);
        $this->assertEquals(0, $results['direct_contacts_count']['Meta']);
        $this->assertEquals(0, $results['expected_bookings']);
    }
}
