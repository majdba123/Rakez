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

class MarketingPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->syncRolesFromType();
    }

    #[Test]
    public function it_can_create_developer_marketing_plan()
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/developer-plans', [
                'contract_id' => $contract->id,
                'marketing_value' => 35000,
                'average_cpm' => 25,
                'average_cpc' => 2.5
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('developer_marketing_plans', [
            'contract_id' => $contract->id,
            'expected_impressions' => 1400000,
            'expected_clicks' => 14000
        ]);
    }

    #[Test]
    public function it_can_auto_generate_employee_plan()
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'commission_percent' => 2.5
        ]);
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/employee-plans/auto-generate', [
                'marketing_project_id' => $project->id,
                'user_id' => $this->marketingUser->id
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employee_marketing_plans', [
            'marketing_project_id' => $project->id,
            'user_id' => $this->marketingUser->id
        ]);
    }

    #[Test]
    public function it_can_create_employee_plan_with_fixed_distributions()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/employee-plans', [
                'marketing_project_id' => $project->id,
                'user_id' => $this->marketingUser->id,
                'commission_value' => 20000,
                'marketing_percent' => 10,
                'marketing_value' => 2000,
                'platform_distribution' => [
                    'TikTok' => 20,
                    'Meta' => 20,
                    'Snapchat' => 20,
                    'YouTube' => 20,
                    'LinkedIn' => 10,
                    'X' => 10,
                ],
                'campaign_distribution_by_platform' => [
                    'Meta' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'TikTok' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'Snapchat' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'YouTube' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'LinkedIn' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'X' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('employee_marketing_plans', [
            'marketing_project_id' => $project->id,
            'user_id' => $this->marketingUser->id,
        ]);
    }

    #[Test]
    public function it_can_store_campaign_distribution_by_platform()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/employee-plans', [
                'marketing_project_id' => $project->id,
                'user_id' => $this->marketingUser->id,
                'commission_value' => 20000,
                'marketing_percent' => 10,
                'marketing_value' => 2000,
                'platform_distribution' => [
                    'TikTok' => 20,
                    'Meta' => 20,
                    'Snapchat' => 20,
                    'YouTube' => 20,
                    'LinkedIn' => 10,
                    'X' => 10,
                ],
                'campaign_distribution_by_platform' => [
                    'Meta' => [
                        'Direct Communication' => 40,
                        'Hand Raise' => 30,
                        'Impression' => 20,
                        'Sales' => 10,
                    ],
                    'TikTok' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'Snapchat' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'YouTube' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'LinkedIn' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                    'X' => [
                        'Direct Communication' => 25,
                        'Hand Raise' => 25,
                        'Impression' => 25,
                        'Sales' => 25,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $byPlatform = $response->json('data.campaign_distribution_by_platform');
        $this->assertEquals(40, $byPlatform['Meta']['Direct Communication']);
    }
}
