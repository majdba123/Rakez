<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\DeveloperMarketingPlan;
use App\Models\EmployeeMarketingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    public function it_can_access_developer_plan_via_alias_route()
    {
        $contract = Contract::factory()->create();
        DeveloperMarketingPlan::create([
            'contract_id' => $contract->id,
            'average_cpm' => 10.5,
            'average_cpc' => 2.5,
            'marketing_value' => 50000,
            'expected_impressions' => 1000000,
            'expected_clicks' => 50000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/plans/developer/{$contract->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function it_can_create_developer_plan_via_alias_route()
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/plans/developer', [
                'contract_id' => $contract->id,
                'average_cpm' => 10.5,
                'average_cpc' => 2.5,
                'marketing_value' => 50000,
                'expected_impressions' => 1000000,
                'expected_clicks' => 50000,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('developer_marketing_plans', [
            'contract_id' => $contract->id,
        ]);
    }

    #[Test]
    public function it_can_access_employee_plans_via_alias_route()
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
            ->getJson('/api/marketing/plans/employee?project_id=' . $project->id);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'marketing_project_id', 'user_id']
                ]
            ]);
    }

    #[Test]
    public function it_can_access_employee_plan_by_id_via_alias_route()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);
        $plan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $project->id,
            'user_id' => $this->marketingUser->id,
            'commission_value' => 1000,
            'marketing_value' => 5000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/plans/employee/{$plan->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'marketing_project_id', 'user_id']
            ]);
    }

    #[Test]
    public function it_can_create_employee_plan_via_alias_route()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $platformDistribution = [
            'TikTok' => 20,
            'Meta' => 25,
            'Snapchat' => 15,
            'YouTube' => 15,
            'LinkedIn' => 10,
            'X' => 15,
        ];
        $campaignDistribution = [
            'Direct Communication' => 25,
            'Hand Raise' => 25,
            'Impression' => 25,
            'Sales' => 25,
        ];
        $campaignByPlatform = [];
        foreach (array_keys($platformDistribution) as $platform) {
            $campaignByPlatform[$platform] = $campaignDistribution;
        }

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/plans/employee', [
                'marketing_project_id' => $project->id,
                'user_id' => $this->marketingUser->id,
                'commission_value' => 1000,
                'marketing_value' => 5000,
                'marketing_percent' => 50,
                'platform_distribution' => $platformDistribution,
                'campaign_distribution_by_platform' => $campaignByPlatform,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('employee_marketing_plans', [
            'marketing_project_id' => $project->id,
            'user_id' => $this->marketingUser->id,
        ]);
    }

    #[Test]
    public function old_routes_still_work_for_backward_compatibility()
    {
        $contract = Contract::factory()->create();
        DeveloperMarketingPlan::create([
            'contract_id' => $contract->id,
            'average_cpm' => 10.5,
            'average_cpc' => 2.5,
            'marketing_value' => 50000,
            'expected_impressions' => 1000000,
            'expected_clicks' => 50000,
        ]);

        // Test old route still works
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/developer-plans/{$contract->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Test new alias route also works
        $response2 = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/plans/developer/{$contract->id}");

        $response2->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
