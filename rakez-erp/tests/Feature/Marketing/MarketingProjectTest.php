<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ProjectMedia;
use App\Models\SalesProjectAssignment;
use App\Models\SalesTeamMemberRating;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingProjectTest extends TestCase
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
    public function it_can_list_marketing_projects()
    {
        $project = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $project->id,
            'avg_property_value' => 500000,
            'agency_number' => 'ADV-555'
        ]);
        ProjectMedia::create([
            'contract_id' => $project->id,
            'type' => 'image',
            'url' => 'https://cdn.example.com/photo-1.jpg',
            'department' => 'photography',
        ]);
        ProjectMedia::create([
            'contract_id' => $project->id,
            'type' => 'video',
            'url' => 'https://cdn.example.com/video-1.mp4',
            'department' => 'montage',
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'contract_id',
                        'project_name',
                        'developer_name',
                        'units_count' => ['available', 'pending'],
                        'avg_unit_price',
                        'advertiser_number',
                        'advertiser_number_value',
                        'advertiser_number_status',
                        'commission_percent',
                        'total_available_value',
                        'media_links',
                        'description',
                    ]
                ]
            ]);

        $this->assertEquals('ADV-555', $response->json('data.0.advertiser_number_value'));
        $this->assertEquals('Available', $response->json('data.0.advertiser_number_status'));
        $this->assertCount(2, $response->json('data.0.media_links'));
        $this->assertDatabaseHas('marketing_projects', [
            'contract_id' => $project->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_can_show_project_details_with_duration_status()
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 100,
            'avg_property_value' => 500000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'project_name',
                    'duration_status' => ['status', 'label', 'days']
                ]
            ]);

        $this->assertDatabaseHas('marketing_projects', [
            'contract_id' => $contract->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function project_details_include_responsible_sales_teams_leaders_members_and_ratings(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 100,
            'avg_property_value' => 500000,
        ]);

        $team = Team::factory()->create();
        $leader = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
        ]);
        $member = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
        ]);
        $contract->teams()->attach($team->id);

        SalesProjectAssignment::create([
            'leader_id' => $leader->id,
            'contract_id' => $contract->id,
            'assigned_by' => $leader->id,
        ]);

        SalesTeamMemberRating::create([
            'leader_id' => $leader->id,
            'member_id' => $member->id,
            'rating' => 4,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.responsible_sales_teams.0.id', $team->id)
            ->assertJsonPath('data.responsible_sales_teams.0.name', $team->name)
            ->assertJsonPath('data.responsible_sales_teams.0.leaders.0.id', $leader->id)
            ->assertJsonStructure([
                'data' => [
                    'responsible_sales_teams' => [
                        [
                            'id',
                            'name',
                            'leaders',
                            'members' => [
                                [
                                    'id',
                                    'name',
                                    'role',
                                    'rating',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $members = collect($response->json('data.responsible_sales_teams.0.members'));
        $memberRow = $members->firstWhere('id', $member->id);
        $this->assertNotNull($memberRow);
        $this->assertSame(4, $memberRow['rating']);
    }

    #[Test]
    public function project_details_infer_sales_leaders_when_no_sales_project_assignment(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 100,
            'avg_property_value' => 500000,
        ]);

        $team = Team::factory()->create(['created_by' => null]);
        $salesUser = User::factory()->create([
            'name' => 'Alpha Sales',
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
            'is_manager' => false,
        ]);
        $contract->teams()->attach($team->id);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.responsible_sales_teams.0.leaders.0.id', $salesUser->id)
            ->assertJsonPath('data.responsible_sales_teams.0.leaders.0.name', 'Alpha Sales');
    }

    #[Test]
    public function project_details_fill_city_from_contract_info_when_city_relation_null(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'city_id' => null,
            'district_id' => null,
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'contract_city' => 'الرياض',
            'second_party_address' => 'حي الملز',
            'agreement_duration_days' => 100,
            'avg_property_value' => 500000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.city.name', 'الرياض')
            ->assertJsonPath('data.district.name', 'حي الملز');
    }

    #[Test]
    public function it_can_calculate_campaign_budget()
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
            ->postJson('/api/marketing/projects/calculate-budget', [
                'contract_id' => $contract->id,
                'unit_price' => 1000000
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.commission_value', 25000)
            ->assertJsonPath('data.marketing_value', 2500);
    }

    #[Test]
    public function it_uses_contract_duration_months_for_monthly_budget_when_available()
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
            ->postJson('/api/marketing/projects/calculate-budget', [
                'contract_id' => $contract->id,
                'unit_price' => 1000000
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.monthly_budget', 1250);
    }
}
