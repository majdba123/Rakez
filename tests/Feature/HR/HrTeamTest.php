<?php

namespace Tests\Feature\HR;

use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrTeamTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        // Create HR permissions
        $permissions = [
            'hr.dashboard.view',
            'hr.teams.manage',
            'hr.employees.manage',
            'hr.performance.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create HR role
        $hrRole = Role::firstOrCreate(['name' => 'hr']);
        $hrRole->syncPermissions($permissions);

        // Create HR user
        $this->hrUser = User::factory()->create([
            'type' => 'hr',
            'is_active' => true,
        ]);
        $this->hrUser->assignRole('hr');

        // Create a team
        $this->team = Team::factory()->create([
            'name' => 'Test Team',
            'description' => 'Test team description',
            'created_by' => $this->hrUser->id,
        ]);
    }

    public function test_hr_user_can_list_teams(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/teams');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'projects_count',
                        'members_count',
                        'avg_target_achievement',
                    ],
                ],
            ]);
    }

    public function test_hr_user_can_create_team(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->postJson('/api/hr/teams', [
                'name' => 'New Team',
                'description' => 'New team description',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Team',
                    'description' => 'New team description',
                ],
            ]);

        $this->assertDatabaseHas('teams', ['name' => 'New Team']);
    }

    public function test_hr_user_can_view_team_details(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson("/api/hr/teams/{$this->team->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ],
            ]);
    }

    public function test_hr_user_can_update_team(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->putJson("/api/hr/teams/{$this->team->id}", [
                'name' => 'Updated Team Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Team Name',
                    'description' => 'Updated description',
                ],
            ]);

        $this->assertDatabaseHas('teams', ['id' => $this->team->id, 'name' => 'Updated Team Name']);
    }

    public function test_hr_user_can_delete_team(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->deleteJson("/api/hr/teams/{$this->team->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('teams', ['id' => $this->team->id]);
    }

    public function test_hr_user_can_assign_member_to_team(): void
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
            'team_id' => null,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->postJson("/api/hr/teams/{$this->team->id}/members", [
                'user_id' => $employee->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'team_id' => $this->team->id]);
    }

    public function test_hr_user_can_remove_member_from_team(): void
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
            'team_id' => $this->team->id,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->deleteJson("/api/hr/teams/{$this->team->id}/members/{$employee->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'team_id' => null]);
    }
}

