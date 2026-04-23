<?php

namespace Tests\Feature\Marketing;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractUnit;
use App\Models\MarketingProject;
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
}
