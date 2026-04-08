<?php

namespace Tests\Feature\Contract;

use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\SalesProjectAssignment;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachContractTeamAutoLeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function attaching_team_to_completed_contract_creates_sales_project_assignment_for_default_leader(): void
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->syncRolesFromType();

        $team = Team::factory()->create();
        $leader = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
            'is_manager' => true,
        ]);

        $contract = Contract::factory()->create([
            'status' => 'completed',
        ]);

        $response = $this->actingAs($pm, 'sanctum')
            ->postJson("/api/project_management/teams/add/{$contract->id}", [
                'team_ids' => [$team->id],
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('sales_project_assignments', [
            'contract_id' => $contract->id,
            'leader_id' => $leader->id,
            'assigned_by' => $pm->id,
        ]);
    }

    #[Test]
    public function single_team_attach_sets_marketing_project_assigned_team_leader_when_empty(): void
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->syncRolesFromType();

        $team = Team::factory()->create();
        $leader = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
            'is_manager' => true,
        ]);

        $contract = Contract::factory()->create([
            'status' => 'completed',
        ]);

        MarketingProject::query()->create([
            'contract_id' => $contract->id,
            'status' => 'active',
            'assigned_team_leader' => null,
        ]);

        $this->actingAs($pm, 'sanctum')
            ->postJson("/api/project_management/teams/add/{$contract->id}", [
                'team_ids' => [$team->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('marketing_projects', [
            'contract_id' => $contract->id,
            'assigned_team_leader' => $leader->id,
        ]);
    }

    #[Test]
    public function attaching_team_does_not_duplicate_active_assignment(): void
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->syncRolesFromType();

        $team = Team::factory()->create();
        $leader = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
            'is_active' => true,
            'is_manager' => true,
        ]);

        $contract = Contract::factory()->create([
            'status' => 'completed',
        ]);
        $contract->teams()->attach($team->id);

        SalesProjectAssignment::create([
            'leader_id' => $leader->id,
            'contract_id' => $contract->id,
            'assigned_by' => $pm->id,
        ]);

        $before = SalesProjectAssignment::where('contract_id', $contract->id)->count();

        $this->actingAs($pm, 'sanctum')
            ->postJson("/api/project_management/teams/add/{$contract->id}", [
                'team_ids' => [$team->id],
            ])
            ->assertStatus(200);

        $this->assertSame($before, SalesProjectAssignment::where('contract_id', $contract->id)->count());
    }
}
