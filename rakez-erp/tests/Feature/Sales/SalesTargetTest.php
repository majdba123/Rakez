<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesTarget;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTargetTest extends TestCase
{
    use RefreshDatabase;

    protected User $leader;
    protected User $marketer;
    protected Contract $contract;
    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->leader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team' => 'Team Alpha',
        ]);
        $this->leader->assignRole('sales_leader');

        $this->marketer = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
            'team' => 'Team Alpha',
        ]);
        $this->marketer->assignRole('sales');

        $this->contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);
        
        $this->unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);
        
        // Assign leader to project
        \App\Models\SalesProjectAssignment::create([
            'leader_id' => $this->leader->id,
            'contract_id' => $this->contract->id,
            'assigned_by' => $this->leader->id,
        ]);
    }

    public function test_leader_can_create_target()
    {
        $data = [
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'target_type' => 'reservation',
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-30',
            'leader_notes' => 'High priority unit',
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', $data);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('sales_targets', [
            'leader_id' => $this->leader->id,
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'target_type' => 'reservation',
            'status' => 'new',
        ]);
    }

    public function test_marketer_cannot_create_target()
    {
        $data = [
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
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
        $otherMarketer = User::factory()->create(['type' => 'sales', 'team' => 'Team Alpha']);
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

    public function test_target_can_be_project_level_without_unit()
    {
        $data = [
            'marketer_id' => $this->marketer->id,
            'contract_id' => $this->contract->id,
            'contract_unit_id' => null,
            'target_type' => 'negotiation',
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-30',
            'leader_notes' => 'Focus on this project',
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/targets', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales_targets', [
            'contract_id' => $this->contract->id,
            'contract_unit_id' => null,
            'target_type' => 'negotiation',
        ]);
    }
}
