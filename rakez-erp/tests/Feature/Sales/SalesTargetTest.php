<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesTarget;
use App\Models\SecondPartyData;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesTargetTest extends TestCase
{
    use RefreshDatabase;

    protected User $leader;
    protected User $marketer;
    protected Team $team;
    protected Team $otherTeam;
    protected Contract $contract;
    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->team = Team::factory()->create(['name' => 'Team Alpha']);
        $this->otherTeam = Team::factory()->create(['name' => 'Team Beta']);

        $this->leader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team' => 'Team Alpha',
            'team_id' => $this->team->id,
        ]);
        $this->leader->assignRole('sales_leader');

        $this->marketer = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
            'team' => 'Team Alpha',
            'team_id' => $this->team->id,
        ]);
        $this->marketer->assignRole('sales');

        $this->contract = Contract::factory()->create(['status' => 'completed']);
        $this->contract->teams()->attach($this->team->id);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);

        $this->unit = ContractUnit::factory()->create([
            'contract_id' => $secondPartyData->contract_id,
            'price' => 500000,
        ]);
    }

    public function test_leader_can_create_target()
    {
        $data = [
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'must_sell_units_count' => 1,
            'assigned_target_value' => 500000,
            'target_type' => 'reservation',
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-30',
            'leader_notes' => 'High priority unit',
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', $data);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.must_sell_units_count', 1);
        $this->assertEqualsWithDelta(
            500000.0,
            (float) $response->json('data.assigned_target_value'),
            0.01
        );

        $tid = $response->json('data.target_id');
        $this->assertSame(0, (int) DB::table('sales_target_units')->where('sales_target_id', $tid)->count());

        $this->assertDatabaseHas('sales_targets', [
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'target_type' => 'reservation',
            'status' => 'new',
            'must_sell_units_count' => 1,
        ]);
    }

    public function test_marketer_cannot_create_target()
    {
        $data = [
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'must_sell_units_count' => 1,
            'target_type' => 'reservation',
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-30',
        ];

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->postJson('/api/sales/targets', $data);

        $response->assertStatus(403);
    }

    public function test_marketer_can_view_their_targets()
    {
        SalesTarget::factory()->count(3)->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'status' => 'new',
        ]);

        // Create target for another marketer
        $otherMarketer = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Alpha',
            'team_id' => $this->team->id,
        ]);
        $otherMarketer->assignRole('sales');
        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $otherMarketer->id,
            'contract_id' => $this->contract->id,
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->getJson('/api/sales/targets/my');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_marketer_can_update_their_target_status()
    {
        $target = SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->patchJson("/api/sales/targets/{$target->id}", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales_targets', [
            'id' => $target->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_marketer_cannot_update_other_marketers_target()
    {
        $otherMarketer = User::factory()->create(['type' => 'sales', 'team' => 'Team Alpha']);
        $otherMarketer->assignRole('sales');

        $target = SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $otherMarketer->id,
            'contract_id' => $this->contract->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->patchJson("/api/sales/targets/{$target->id}", [
                'status' => 'completed',
            ]);

        $response->assertStatus(403);
    }

    public function test_filter_targets_by_status()
    {
        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'status' => 'new',
        ]);

        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'status' => 'in_progress',
        ]);

        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->getJson('/api/sales/targets/my?status=in_progress');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_filter_targets_by_date_range()
    {
        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-10',
        ]);

        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-31',
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->getJson('/api/sales/targets/my?from=2025-01-15&to=2025-01-31');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_leader_sees_assigned_projects_on_my()
    {
        $otherContract = Contract::factory()->create(['status' => 'completed']);
        $otherContract->teams()->attach($this->otherTeam->id);

        $unlinkedContract = Contract::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->getJson('/api/sales/targets/my');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Leader should see at least the assigned project');
        $this->assertCount(1, $data);

        $first = $data[0];
        $this->assertSame('project_assignment', $first['item_type'] ?? null);
        $this->assertSame($this->contract->id, $first['contract_id']);
        $this->assertSame(null, $first['assignment_id']);
        $this->assertSame('Project Management', $first['assigned_by']);
        $this->assertArrayHasKey('project_name', $first);
        $this->assertArrayHasKey('project_location', $first);
        $this->assertArrayHasKey('city_name', $first['project_location'] ?? []);
    }

    public function test_target_json_includes_project_location_for_marketer()
    {
        $target = SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->getJson('/api/sales/targets/my');

        $response->assertStatus(200);
        $row = $response->json('data.0');
        $this->assertArrayHasKey('project_location', $row);
        $this->assertArrayHasKey('must_sell_units_count', $row);
        $this->assertArrayHasKey('assigned_target_value', $row);
        $this->assertSame($target->id, $row['target_id']);
    }

    public function test_store_derives_must_sell_units_count_from_legacy_contract_unit_ids()
    {
        $secondUnit = ContractUnit::factory()->create([
            'contract_id' => $this->contract->id,
            'price' => 300000,
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', [
                'marketer_id' => $this->marketer->id,
                'contract_id' => $this->contract->id,
                'contract_unit_ids' => [$this->unit->id, $secondUnit->id],
                'target_type' => 'reservation',
                'start_date' => '2025-01-20',
                'end_date' => '2025-01-30',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.must_sell_units_count', 2);
    }

    public function test_target_can_be_project_level_without_unit()
    {
        $data = [
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'must_sell_units_count' => 3,
            'target_type' => 'negotiation',
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-30',
            'leader_notes' => 'Focus on this project',
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.must_sell_units_count', 3);

        $this->assertDatabaseHas('sales_targets', [
            'contract_id' => $this->contract->id,
            'contract_unit_id' => null,
            'target_type' => 'negotiation',
            'must_sell_units_count' => 3,
        ]);
    }

    public function test_leader_can_create_target_for_multiple_units()
    {
        ContractUnit::factory()->create([
            'contract_id' => $this->contract->id,
            'price' => 650000,
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', [
                'marketer_id' => $this->marketer->id,
                'contract_id' => $this->contract->id,
                'must_sell_units_count' => 2,
                'target_type' => 'closing',
                'start_date' => '2025-01-20',
                'end_date' => '2025-01-30',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.must_sell_units_count', 2)
            ->assertJsonPath('data.contract_unit_ids', []);

        $tid = $response->json('data.target_id');
        $this->assertSame(0, (int) DB::table('sales_target_units')->where('sales_target_id', $tid)->count());
    }

    public function test_leader_cannot_create_target_for_project_outside_team()
    {
        $otherContract = Contract::factory()->create(['status' => 'completed']);
        $otherContract->teams()->attach($this->otherTeam->id);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', [
                'marketer_id' => $this->marketer->id,
                'contract_id' => $otherContract->id,
                'must_sell_units_count' => 1,
                'target_type' => 'reservation',
                'start_date' => '2025-01-20',
                'end_date' => '2025-01-30',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_leader_cannot_create_target_for_marketer_outside_team()
    {
        $otherMarketer = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
            'team' => 'Team Beta',
            'team_id' => $this->otherTeam->id,
        ]);
        $otherMarketer->assignRole('sales');

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', [
                'marketer_id' => $otherMarketer->id,
                'contract_id' => $this->contract->id,
                'must_sell_units_count' => 1,
                'target_type' => 'reservation',
                'start_date' => '2025-01-20',
                'end_date' => '2025-01-30',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_leader_cannot_create_target_for_sales_manager()
    {
        $otherLeader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team' => 'Team Alpha',
            'team_id' => $this->team->id,
        ]);
        $otherLeader->assignRole('sales_leader');

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', [
                'marketer_id' => $otherLeader->id,
                'contract_id' => $this->contract->id,
                'must_sell_units_count' => 1,
                'target_type' => 'reservation',
                'start_date' => '2025-01-20',
                'end_date' => '2025-01-30',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_marketer_by_project_shows_only_own_targets()
    {
        $otherMarketer = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Alpha',
            'team_id' => $this->team->id,
        ]);
        $otherMarketer->assignRole('sales');

        $ownTarget = SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
        ]);

        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $otherMarketer->id,
            'contract_id' => $this->contract->id,
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->getJson("/api/sales/targets/by-project/{$this->contract->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_id', $ownTarget->id);
    }

    public function test_leader_by_project_shows_only_targets_of_team_marketers_for_pm_team_project()
    {
        $otherTeamMarketer = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Beta',
            'team_id' => $this->otherTeam->id,
        ]);
        $otherTeamMarketer->assignRole('sales');

        $teamTarget = SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
        ]);

        SalesTarget::factory()->create([
            'leader_id' => $this->leader->id,
            'marketer_id' => $otherTeamMarketer->id,
            'contract_id' => $this->contract->id,
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->getJson("/api/sales/targets/by-project/{$this->contract->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_id', $teamTarget->id);
    }
}
