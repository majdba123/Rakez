<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_project_management_can_assign_sales_member_to_team(): void
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->assignRole('project_management');

        $team = Team::factory()->create(['created_by' => $pm->id]);
        $sales = User::factory()->create([
            'type' => 'sales',
            'team_id' => null,
        ]);

        $response = $this->actingAs($pm)->postJson("/api/project_management/teams/{$team->id}/members", [
            'user_id' => $sales->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $sales->id)
            ->assertJsonPath('data.team_id', $team->id);

        $this->assertDatabaseHas('users', ['id' => $sales->id, 'team_id' => $team->id]);
    }

    public function test_project_management_cannot_assign_non_sales_member(): void
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->assignRole('project_management');

        $team = Team::factory()->create(['created_by' => $pm->id]);
        $marketing = User::factory()->create([
            'type' => 'marketing',
            'team_id' => null,
        ]);

        $response = $this->actingAs($pm)->postJson("/api/project_management/teams/{$team->id}/members", [
            'user_id' => $marketing->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $marketing->id, 'team_id' => null]);
    }

    public function test_project_management_can_remove_member_from_team(): void
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->assignRole('project_management');

        $team = Team::factory()->create(['created_by' => $pm->id]);
        $sales = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
        ]);

        $response = $this->actingAs($pm)->deleteJson("/api/project_management/teams/{$team->id}/members/{$sales->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['id' => $sales->id, 'team_id' => null]);
    }

    public function test_project_management_can_assign_team_sales_leader_once(): void
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->assignRole('project_management');

        $team = Team::factory()->create(['created_by' => $pm->id]);
        $leader = User::factory()->create([
            'type' => 'sales_leader',
            'team_id' => null,
        ]);
        if (method_exists($leader, 'assignRole')) {
            $leader->assignRole('sales_leader');
        }

        $r = $this->actingAs($pm)->postJson("/api/project_management/teams/{$team->id}/sales-leader", [
            'user_id' => $leader->id,
        ]);
        $r->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', [
            'id' => $leader->id,
            'team_id' => $team->id,
            'team_group_id' => null,
        ]);

        $other = User::factory()->create(['type' => 'sales_leader', 'team_id' => null]);
        if (method_exists($other, 'assignRole')) {
            $other->assignRole('sales_leader');
        }

        $r2 = $this->actingAs($pm)->postJson("/api/project_management/teams/{$team->id}/sales-leader", [
            'user_id' => $other->id,
        ]);
        $r2->assertStatus(422)->assertJsonPath('success', false);
    }
}
