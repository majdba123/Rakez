<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
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

    /** @test */
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

    /** @test */
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
}
