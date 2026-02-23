<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_access_everything()
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');

        $contract = Contract::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/contracts/show/{$contract->id}");

        $response->assertStatus(200);
    }

    public function test_user_can_view_own_contract()
    {
        $user = User::factory()->create(['type' => 'user']);
        // Assign default capabilities if needed, or ensure they have basic access
        // Assuming 'default' role or no role but own contract access
        
        $contract = Contract::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/contracts/show/{$contract->id}");

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_others_contract()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $contract = Contract::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson("/api/contracts/show/{$contract->id}");

        $response->assertStatus(403);
    }

    public function test_manager_can_view_team_contracts()
    {
        $manager = User::factory()->create([
            'type' => 'project_management',
            'is_manager' => true,
            'team' => 'sales'
        ]);
        $manager->assignRole('project_management'); // Assuming PM role has view access

        $employee = User::factory()->create([
            'team' => 'sales'
        ]);

        $contract = Contract::factory()->create(['user_id' => $employee->id]);

        // Manager should see it
        $response = $this->actingAs($manager)->getJson("/api/contracts/show/{$contract->id}");
        $response->assertStatus(200);
    }

    public function test_manager_cannot_view_other_team_contracts()
    {
        $manager = User::factory()->create([
            'type' => 'project_management',
            'is_manager' => true,
            'team' => 'sales'
        ]);
        $manager->assignRole('project_management');

        $otherEmployee = User::factory()->create([
            'team' => 'marketing'
        ]);

        $contract = Contract::factory()->create(['user_id' => $otherEmployee->id]);

        // Manager should NOT see it (unless they have global view permission)
        // 'project_management' role has 'contracts.view_all' in config?
        // Let's check config.
        // 'project_management' => ['contracts.view', 'contracts.view_all', ...]
        // If they have view_all, they can see everything.
        
        // Let's create a custom manager role WITHOUT view_all for this test
        $customManager = User::factory()->create([
            'is_manager' => true,
            'team' => 'sales'
        ]);
        $customManager->givePermissionTo('contracts.view'); // Basic view only

        $response = $this->actingAs($customManager)->getJson("/api/contracts/show/{$contract->id}");
        $response->assertStatus(403);
    }
}
