<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Team;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;
use App\Models\MarketingCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingTeamsTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->assignRole('marketing');
        $this->marketingUser->givePermissionTo('marketing.teams.view');
        $this->marketingUser->givePermissionTo('marketing.teams.manage');
    }

    #[Test]
    public function it_can_list_marketing_teams()
    {
        Team::factory()->count(3)->create();

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'description']
                ]
            ])
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function it_can_assign_campaign_to_team()
    {
        $team = Team::factory()->create();
        $salesUser = User::factory()->create(['type' => 'sales', 'team_id' => $team->id]);
        
        $contract = Contract::factory()->create();
        $marketingProject = MarketingProject::create(['contract_id' => $contract->id]);
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'commission_value' => 1000,
            'marketing_value' => 5000,
        ]);
        $campaign = MarketingCampaign::create([
            'employee_marketing_plan_id' => $employeePlan->id,
            'platform' => 'Facebook',
            'campaign_type' => 'awareness',
            'budget' => 1000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/teams/assign', [
                'team_id' => $team->id,
                'campaign_id' => $campaign->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Campaign assigned to team successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['team_id', 'campaign_id', 'assigned_members']
            ]);
    }

    #[Test]
    public function it_requires_team_id_and_campaign_id()
    {
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/teams/assign', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_id', 'campaign_id']);
    }

    #[Test]
    public function it_validates_team_exists()
    {
        $campaign = MarketingCampaign::factory()->create();

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/teams/assign', [
                'team_id' => 99999,
                'campaign_id' => $campaign->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    #[Test]
    public function it_validates_campaign_exists()
    {
        $team = Team::factory()->create();

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/teams/assign', [
                'team_id' => $team->id,
                'campaign_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['campaign_id']);
    }

    #[Test]
    public function it_requires_permission_to_view_teams()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->assignRole('marketing');
        // Remove role and explicitly revoke permission to test authorization
        $user->removeRole('marketing');
        $user->revokePermissionTo('marketing.teams.view');
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/marketing/teams');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_requires_permission_to_assign_campaign()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->assignRole('marketing');
        // Remove role and explicitly revoke permission to test authorization
        $user->removeRole('marketing');
        $user->revokePermissionTo('marketing.teams.manage');
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $team = Team::factory()->create();
        $contract = Contract::factory()->create();
        $marketingProject = MarketingProject::create(['contract_id' => $contract->id]);
        $employeePlan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $marketingProject->id,
            'user_id' => $this->marketingUser->id,
            'commission_value' => 1000,
            'marketing_value' => 5000,
        ]);
        $campaign = MarketingCampaign::create([
            'employee_marketing_plan_id' => $employeePlan->id,
            'platform' => 'Facebook',
            'campaign_type' => 'awareness',
            'budget' => 1000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/marketing/teams/assign', [
                'team_id' => $team->id,
                'campaign_id' => $campaign->id,
            ]);

        $response->assertStatus(403);
    }
}
