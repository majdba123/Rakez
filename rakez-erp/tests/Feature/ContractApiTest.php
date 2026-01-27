<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ContractApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_can_list_own_contracts()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('contracts.view');
        
        Contract::factory()->count(3)->create(['user_id' => $user->id]);
        Contract::factory()->count(2)->create(); // Others' contracts

        $response = $this->actingAs($user)->getJson('/api/contracts/index');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'project_name', 'status', 'created_at'
                    ]
                ]
            ]);
    }

    public function test_user_can_create_contract()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('contracts.create');

        $data = [
            'project_name' => 'New Project',
            'developer_name' => 'Dev Name',
            'developer_number' => '123456',
            'city' => 'Riyadh',
            'district' => 'Olaya',
            'developer_requiment' => 'None',
            'units' => [
                ['type' => 'A', 'count' => 10, 'price' => 100000]
            ]
        ];

        $response = $this->actingAs($user)->postJson('/api/contracts/store', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.project_name', 'New Project')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('contracts', [
            'project_name' => 'New Project',
            'user_id' => $user->id
        ]);
    }

    public function test_user_can_show_own_contract()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('contracts.view');
        $contract = Contract::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/contracts/show/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $contract->id);
    }

    public function test_user_cannot_show_others_contract()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('contracts.view');
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson("/api/contracts/show/{$contract->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_own_pending_contract()
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'project_name' => 'Old Name'
        ]);

        $data = ['project_name' => 'Updated Name'];

        $response = $this->actingAs($user)->putJson("/api/contracts/update/{$contract->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.project_name', 'Updated Name');
    }

    public function test_user_cannot_update_approved_contract()
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved'
        ]);

        $data = ['project_name' => 'Updated Name'];

        $response = $this->actingAs($user)->putJson("/api/contracts/update/{$contract->id}", $data);

        $response->assertStatus(422); // Or 403 depending on logic, service throws exception which controller catches
    }

    public function test_user_can_delete_own_pending_contract()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('contracts.delete'); // Assuming delete requires permission or just ownership? Policy says Owner OR permission.
        
        $contract = Contract::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/contracts/{$contract->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($contract);
    }

    public function test_admin_can_update_status()
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');
        
        $contract = Contract::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($admin)->patchJson("/api/admin/contracts/adminUpdateStatus/{$contract->id}", [
            'status' => 'approved'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_project_management_can_update_status_to_ready()
    {
        $pm = User::factory()->create(['type' => 'project_management']);
        $pm->assignRole('project_management');
        
        // Contract must be approved first, and have requirements for 'ready' status
        $contract = Contract::factory()->create(['status' => 'approved']);
        
        // Mock requirements: SecondPartyData and Units
        $secondParty = \App\Models\SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        \App\Models\ContractUnit::factory()->create(['second_party_data_id' => $secondParty->id]);

        $response = $this->actingAs($pm)->patchJson("/api/contracts/update-status/{$contract->id}", [
            'status' => 'ready'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');
    }
}
