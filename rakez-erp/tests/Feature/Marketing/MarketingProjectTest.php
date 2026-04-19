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
use App\Models\ContractUnit;
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
    public function avg_unit_price_is_calculated_from_the_correct_contract_units_only(): void
    {
        // Contract A: 3 units with prices 100k, 200k, 300k → avg = 200k
        $contractA = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contractA->id, 'avg_property_value' => 9000000]);
        ContractUnit::factory()->create(['contract_id' => $contractA->id, 'price' => 100000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contractA->id, 'price' => 200000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contractA->id, 'price' => 300000, 'status' => 'available']);

        // Contract B: 2 units with prices 400k, 600k → avg = 500k
        $contractB = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contractB->id, 'avg_property_value' => 10000000]);
        ContractUnit::factory()->create(['contract_id' => $contractB->id, 'price' => 400000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contractB->id, 'price' => 600000, 'status' => 'available']);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200);
        $data = collect($response->json('data'));

        $rowA = $data->firstWhere('contract_id', $contractA->id);
        $rowB = $data->firstWhere('contract_id', $contractB->id);

        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        // Must reflect real unit prices — NOT the stale avg_property_value stored in contract_infos
        $this->assertEquals(200000.00, $rowA['avg_unit_price']);
        $this->assertEquals(500000.00, $rowB['avg_unit_price']);
    }

    #[Test]
    public function avg_unit_price_is_not_duplicated_by_related_joins(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 300000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 300000, 'status' => 'available']);

        // Media rows — the kind that could cause duplication in a JOIN-based query
        ProjectMedia::create(['contract_id' => $contract->id, 'type' => 'image', 'url' => 'https://cdn.example.com/a.jpg', 'department' => 'photography']);
        ProjectMedia::create(['contract_id' => $contract->id, 'type' => 'image', 'url' => 'https://cdn.example.com/b.jpg', 'department' => 'photography']);
        ProjectMedia::create(['contract_id' => $contract->id, 'type' => 'video', 'url' => 'https://cdn.example.com/c.mp4', 'department' => 'montage']);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('contract_id', $contract->id);

        $this->assertNotNull($row);
        // Average must be 300k — media rows must not inflate it
        $this->assertEquals(300000.00, $row['avg_unit_price']);
    }

    #[Test]
    public function avg_unit_price_uses_the_correct_price_field_from_contract_units(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        // avg_property_value set to a completely different value to prove it is NOT the source
        ContractInfo::factory()->create(['contract_id' => $contract->id, 'avg_property_value' => 9999999]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 750000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 250000, 'status' => 'sold']);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('contract_id', $contract->id);

        // Must NOT return avg_property_value (9999999); must return avg of unit prices
        $this->assertNotEquals(9999999, $row['avg_unit_price']);
        $this->assertEquals(500000.00, $row['avg_unit_price']);
    }

    #[Test]
    public function avg_unit_price_uses_all_contract_units_not_only_available(): void
    {
        // Business rule: average across ALL units in the contract (not just available)
        // This matches ContractPricingBasisService::resolve() which uses average_unit_price_all
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 200000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 400000, 'status' => 'sold']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 600000, 'status' => 'pending']);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('contract_id', $contract->id);

        // All 3 units: (200k + 400k + 600k) / 3 = 400k
        // Available only would give 200k — that would be wrong
        $this->assertEquals(400000.00, $row['avg_unit_price']);
    }

    #[Test]
    public function total_available_value_and_units_count_remain_consistent(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 100000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 200000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 300000, 'status' => 'pending']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 400000, 'status' => 'sold']);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('contract_id', $contract->id);

        // units_count scoped to available/pending only
        $this->assertEquals(2, $row['units_count']['available']);
        $this->assertEquals(1, $row['units_count']['pending']);

        // total_available_value = sum of available unit prices only
        $this->assertEquals(300000.00, (float) $row['total_available_value']);

        // avg_unit_price = average across ALL 4 units: (100k+200k+300k+400k)/4 = 250k
        $this->assertEquals(250000.00, $row['avg_unit_price']);
    }

    #[Test]
    public function zero_price_units_are_included_in_avg_calculation(): void
    {
        // Units with price = 0 are real units in the contract; they should be counted
        // to avoid inflating the average (zero price = unpriced, not excluded)
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 600000, 'status' => 'available']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'price' => 0, 'status' => 'available']);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('contract_id', $contract->id);

        // (600k + 0) / 2 = 300k — not 600k (which would be if zero-price units were excluded)
        $this->assertEquals(300000.00, $row['avg_unit_price']);
    }

    #[Test]
    public function avg_unit_price_is_zero_when_contract_has_no_units(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id, 'avg_property_value' => 5000000]);
        // No ContractUnit rows created

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('contract_id', $contract->id);

        $this->assertEquals(0, $row['avg_unit_price']);
    }

    #[Test]
    public function project_show_includes_pricing_source_without_campaign_calculator_payload(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'contract_number' => 'CNT-100',
            'agreement_duration_days' => 30,
            'avg_property_value' => 1000000,
        ]);
        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'price' => 500000,
        ]);
        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'sold',
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.pricing_source.contract_number', 'CNT-100')
            ->assertJsonPath('data.pricing_source.commission_value', 25000)
            ->assertJsonPath('data.pricing_source.pricing_basis.source', 'unit_prices_sum_all');

        $payload = $response->json('data');
        $this->assertArrayNotHasKey('marketing_value', $payload['pricing_source']);
        $this->assertArrayNotHasKey('daily_budget', $payload['pricing_source']);
    }
}
