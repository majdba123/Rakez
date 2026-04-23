<?php

namespace Tests\Feature\Marketing;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractUnit;
use App\Models\MarketingProject;
use App\Models\ProjectMedia;
use App\Models\SalesProjectAssignment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingProjectDetailRelationsTest extends TestCase
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
    public function show_accepts_marketing_project_id_from_list_and_returns_real_linked_detail_relations(): void
    {
        Contract::factory()->create(['status' => 'completed']);

        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
            'project_name' => 'Linked Marketing Project',
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        $team = Team::factory()->create();
        $leader = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
            'is_manager' => true,
        ]);
        $member = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
        ]);

        $marketingProject = MarketingProject::factory()->create([
            'contract_id' => $contract->id,
            'assigned_team_leader' => $leader->id,
            'status' => 'active',
        ]);

        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 100000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 300000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending', 'price' => 900000]);

        SalesProjectAssignment::create([
            'leader_id' => $leader->id,
            'contract_id' => $contract->id,
            'assigned_by' => $member->id,
        ]);

        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects')
            ->assertOk();

        $listRow = collect($listResponse->json('data'))->firstWhere('contract_id', $contract->id);
        $this->assertNotNull($listRow);
        $this->assertSame($marketingProject->id, $listRow['id']);
        $this->assertNotSame($contract->id, $listRow['id']);

        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$listRow['id']}")
            ->assertOk();

        $showResponse
            ->assertJsonPath('data.contract_id', $contract->id)
            ->assertJsonPath('data.project_name', 'Linked Marketing Project')
            ->assertJsonPath('data.marketing_project.id', $marketingProject->id)
            ->assertJsonCount(3, 'data.contract_units')
            ->assertJsonCount(2, 'data.available_contract_units')
            ->assertJsonCount(1, 'data.sales_project_assignments')
            ->assertJsonPath('data.sales_project_assignments.0.contract_id', $contract->id)
            ->assertJsonCount(1, 'data.teams')
            ->assertJsonPath('data.teams.0.id', $team->id)
            ->assertJsonCount(1, 'data.responsible_sales_teams')
            ->assertJsonPath('data.responsible_sales_teams.0.id', $team->id)
            ->assertJsonPath('data.units_count.available', 2)
            ->assertJsonPath('data.units_count.pending', 1)
            ->assertJsonPath('data.avg_unit_price', 200000)
            ->assertJsonPath('data.total_available_value', 400000);

        $this->assertSame($listRow['avg_unit_price'], $showResponse->json('data.avg_unit_price'));
        $this->assertSame($listRow['total_available_value'], $showResponse->json('data.total_available_value'));
        $this->assertSame($listRow['units_count'], $showResponse->json('data.units_count'));
    }

    #[Test]
    public function show_returns_empty_relation_arrays_only_when_no_related_rows_exist(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}")
            ->assertOk();

        $response
            ->assertJsonCount(0, 'data.contract_units')
            ->assertJsonCount(0, 'data.available_contract_units')
            ->assertJsonCount(0, 'data.teams')
            ->assertJsonCount(0, 'data.sales_project_assignments')
            ->assertJsonPath('data.units_count.available', 0)
            ->assertJsonPath('data.units_count.pending', 0)
            ->assertJsonPath('data.avg_unit_price', 0)
            ->assertJsonPath('data.total_available_value', 0);

        $this->assertNotNull($response->json('data.marketing_project'));
    }

    #[Test]
    public function show_excludes_soft_deleted_units_from_payloads_and_metrics(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'price' => 100000,
        ]);
        $deletedUnit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'price' => 900000,
        ]);
        $deletedUnit->delete();

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}")
            ->assertOk();

        $response
            ->assertJsonCount(1, 'data.contract_units')
            ->assertJsonCount(1, 'data.available_contract_units')
            ->assertJsonPath('data.units_count.available', 1)
            ->assertJsonPath('data.avg_unit_price', 100000)
            ->assertJsonPath('data.total_available_value', 100000)
            ->assertJsonPath('data.unit_statistics.all_units_count', 1);
    }

    #[Test]
    public function show_uses_actual_units_as_pricing_truth_when_no_units_are_available(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 9000000,
        ]);

        foreach (range(1, 10) as $index) {
            ContractUnit::factory()->create([
                'contract_id' => $contract->id,
                'status' => $index % 2 === 0 ? 'pending' : 'sold',
                'price' => 825000,
            ]);
        }

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}")
            ->assertOk();

        $response
            ->assertJsonPath('data.avg_unit_price', 0)
            ->assertJsonPath('data.total_available_value', 0)
            ->assertJsonPath('data.pricing_source.total_unit_price', 0)
            ->assertJsonPath('data.pricing_source.total_unit_price_all_sum', 8250000)
            ->assertJsonPath('data.pricing_source.average_unit_price_available', 0)
            ->assertJsonPath('data.pricing_source.average_unit_price_all', 825000)
            ->assertJsonPath('data.unit_statistics.total_unit_price_all_sum', 8250000)
            ->assertJsonPath('data.unit_statistics.average_unit_price_all', 825000)
            ->assertJsonPath('data.pricing_source.pricing_basis.avg_property_value_stored', 9000000)
            ->assertJsonPath('data.pricing_source.pricing_basis.stored_fallback_applied', false)
            ->assertJsonPath('data.pricing_source.pricing_basis.stored_fallback_forbidden_reason', 'actual_contract_units_exist')
            ->assertJsonPath('data.pricing.source_of_truth', 'actual_contract_units');
    }

    #[Test]
    public function show_explicitly_flags_stored_fallback_only_when_no_actual_units_exist(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 9000000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}")
            ->assertOk();

        $response
            ->assertJsonCount(0, 'data.contract_units')
            ->assertJsonPath('data.pricing_source.source_of_truth', 'contract_infos.avg_property_value')
            ->assertJsonPath('data.pricing_source.total_unit_price', 9000000)
            ->assertJsonPath('data.pricing_source.average_unit_price_all', 0)
            ->assertJsonPath('data.pricing_source.pricing_basis.source', 'avg_property_value_stored')
            ->assertJsonPath('data.pricing_source.pricing_basis.stored_fallback_applied', true);
    }

    #[Test]
    public function show_loads_marketing_project_team_leader_team_consistently_with_sales_assignment_leader_team(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        $team = Team::factory()->create();
        $leader = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
            'is_manager' => true,
        ]);

        MarketingProject::factory()->create([
            'contract_id' => $contract->id,
            'assigned_team_leader' => $leader->id,
        ]);
        SalesProjectAssignment::create([
            'leader_id' => $leader->id,
            'contract_id' => $contract->id,
            'assigned_by' => $leader->id,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}")
            ->assertOk();

        $response
            ->assertJsonPath('data.marketing_project.team_leader.id', $leader->id)
            ->assertJsonPath('data.marketing_project.team_leader.team.id', $team->id)
            ->assertJsonPath('data.sales_project_assignments.0.leader.id', $leader->id)
            ->assertJsonPath('data.sales_project_assignments.0.leader.team.id', $team->id);
    }

    #[Test]
    public function show_deduplicates_project_media_by_type_and_url_while_preserving_departments(): void
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        ProjectMedia::create(['contract_id' => $contract->id, 'type' => 'image', 'url' => 'https://cdn.example.com/a.jpg', 'department' => 'photography']);
        ProjectMedia::create(['contract_id' => $contract->id, 'type' => 'image', 'url' => 'https://cdn.example.com/a.jpg', 'department' => 'photography']);
        ProjectMedia::create(['contract_id' => $contract->id, 'type' => 'image', 'url' => 'https://cdn.example.com/a.jpg', 'department' => 'montage']);
        ProjectMedia::create(['contract_id' => $contract->id, 'type' => 'video', 'url' => 'https://cdn.example.com/a.jpg', 'department' => 'montage']);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}")
            ->assertOk();

        $response
            ->assertJsonCount(2, 'data.project_media')
            ->assertJsonCount(2, 'data.media_links')
            ->assertJsonPath('data.project_media.0.url', 'https://cdn.example.com/a.jpg')
            ->assertJsonPath('data.project_media.0.departments.0', 'photography')
            ->assertJsonPath('data.project_media.0.departments.1', 'montage')
            ->assertJsonCount(3, 'data.project_media.0.source_media_ids');
    }

    #[Test]
    public function show_separates_legacy_contract_units_summary_from_actual_contract_unit_rows(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'units' => [
                ['type' => 'legacy-villa', 'count' => 2, 'price' => 900000],
            ],
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'unit_type' => 'actual-apartment',
            'status' => 'available',
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}")
            ->assertOk();

        $response
            ->assertJsonPath('data.legacy_contract_units_summary.source', 'contracts.units')
            ->assertJsonPath('data.legacy_contract_units_summary.items.0.type', 'legacy-villa')
            ->assertJsonPath('data.actual_unit_data.source', 'contract_units')
            ->assertJsonPath('data.actual_unit_data.all_contract_units.0.unit_type', 'actual-apartment')
            ->assertJsonPath('data.contract_units.0.unit_type', 'actual-apartment')
            ->assertJsonPath('data.units.0.type', 'legacy-villa');
    }
}
