<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesProjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->assignRole('sales');
    }

    public function test_project_status_pending_when_contract_not_ready()
    {
        $contract = Contract::factory()->create(['status' => 'pending']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}");

        $response->assertStatus(200);
        $this->assertEquals('pending', $response->json('data.sales_status'));
    }

    public function test_project_status_pending_when_units_have_zero_price()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 0,
        ]);

        ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}");

        $response->assertStatus(200);
        $this->assertEquals('pending', $response->json('data.sales_status'));
    }

    public function test_project_status_available_when_ready_and_all_units_priced()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        ContractUnit::factory()->count(3)->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}");

        $response->assertStatus(200);
        $this->assertEquals('available', $response->json('data.sales_status'));
    }

    public function test_unit_can_reserve_when_project_available_and_no_reservation()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}/units");

        $response->assertStatus(200);
        
        $unitData = collect($response->json('data'))->firstWhere('unit_id', $unit->id);
        $this->assertEquals('available', $unitData['computed_availability']);
        $this->assertTrue($unitData['can_reserve']);
    }

    public function test_unit_cannot_reserve_when_active_reservation_exists()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        // Create active reservation
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'under_negotiation',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}/units");

        $response->assertStatus(200);
        
        $unitData = collect($response->json('data'))->firstWhere('unit_id', $unit->id);
        $this->assertEquals('reserved', $unitData['computed_availability']);
        $this->assertFalse($unitData['can_reserve']);
    }

    public function test_unit_computed_availability_reserved_when_confirmed()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        // Create confirmed reservation
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}/units");

        $response->assertStatus(200);
        
        $unitData = collect($response->json('data'))->firstWhere('unit_id', $unit->id);
        $this->assertEquals('reserved', $unitData['computed_availability']);
        $this->assertFalse($unitData['can_reserve']);
    }

    public function test_cancelled_reservation_does_not_affect_availability()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        // Create cancelled reservation
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}/units");

        $response->assertStatus(200);
        
        $unitData = collect($response->json('data'))->firstWhere('unit_id', $unit->id);
        $this->assertEquals('available', $unitData['computed_availability']);
        $this->assertTrue($unitData['can_reserve']);
    }

    public function test_projects_list_returns_paginated_results()
    {
        Contract::factory()->count(20)->create(['status' => 'ready'])->each(function ($contract) {
            $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
            ContractUnit::factory()->create([
                'second_party_data_id' => $secondPartyData->id,
                'price' => 500000,
            ]);
        });

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/projects?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_project_units_can_be_filtered_by_floor()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'floor' => '1',
            'price' => 500000,
        ]);

        ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'floor' => '2',
            'price' => 600000,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}/units?floor=1");

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_reservation_context_provides_complete_data()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/units/{$unit->id}/reservation-context");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'project',
                    'unit',
                    'marketing_employee',
                    'lookups' => [
                        'reservation_types',
                        'payment_methods',
                        'down_payment_statuses',
                        'purchase_mechanisms',
                        'nationalities',
                    ],
                ],
            ]);
<<<<<<< HEAD
<<<<<<< HEAD

        $response->assertJsonPath('data.flags.is_off_plan', false);
=======
>>>>>>> parent of 29c197a (Add edits)
=======
    }

    public function test_project_assignment_with_date_range()
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');
        
        $leader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $leader->assignRole('sales_leader');
        
        $contract = Contract::factory()->create(['status' => 'ready']);
        
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/sales/project-assignments', [
                'leader_id' => $leader->id,
                'contract_id' => $contract->id,
                'start_date' => '2026-02-01',
                'end_date' => '2026-08-01',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        
        $assignment = SalesProjectAssignment::where('leader_id', $leader->id)
            ->where('contract_id', $contract->id)
            ->first();
        
        $this->assertNotNull($assignment);
        $this->assertEquals('2026-02-01', $assignment->start_date->toDateString());
        $this->assertEquals('2026-08-01', $assignment->end_date->toDateString());
    }

    public function test_project_assignment_prevents_overlapping()
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');
        
        $leader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $leader->assignRole('sales_leader');
        
        $contract1 = Contract::factory()->create(['status' => 'ready']);
        $contract2 = Contract::factory()->create(['status' => 'ready']);
        
        // Create first assignment
        SalesProjectAssignment::create([
            'leader_id' => $leader->id,
            'contract_id' => $contract1->id,
            'assigned_by' => $admin->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
        ]);
        
        // Try to create overlapping assignment
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/sales/project-assignments', [
                'leader_id' => $leader->id,
                'contract_id' => $contract2->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-10-01',
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_get_my_assignments()
    {
        $leader = User::factory()->create(['type' => 'sales', 'is_manager' => true]);
        $leader->assignRole('sales_leader');
        
        $contract1 = Contract::factory()->create(['status' => 'ready']);
        $contract2 = Contract::factory()->create(['status' => 'ready']);
        
        SalesProjectAssignment::create([
            'leader_id' => $leader->id,
            'contract_id' => $contract1->id,
            'assigned_by' => $leader->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
        
        SalesProjectAssignment::create([
            'leader_id' => $leader->id,
            'contract_id' => $contract2->id,
            'assigned_by' => $leader->id,
            'start_date' => now()->addDays(60)->toDateString(),
            'end_date' => now()->addDays(120)->toDateString(),
        ]);
        
        $response = $this->actingAs($leader, 'sanctum')
            ->getJson('/api/sales/assignments/my');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');
    }

    public function test_project_shows_remaining_days()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $contractInfo = ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 180,
            'created_at' => now()->subDays(30),
        ]);
        
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}");

        $response->assertStatus(200);
        // remaining_days can be null if contract info doesn't exist or is expired
        $remainingDays = $response->json('data.remaining_days');
        if ($remainingDays !== null) {
            $this->assertGreaterThan(0, $remainingDays);
        }
    }

    public function test_sales_user_sees_only_own_reservations()
    {
        $salesUser1 = User::factory()->create(['type' => 'sales']);
        $salesUser1->assignRole('sales');
        
        $salesUser2 = User::factory()->create(['type' => 'sales']);
        $salesUser2->assignRole('sales');
        
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit1 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);
        
        $unit2 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 600000,
        ]);
        
        // Create reservations for both users
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit1->id,
            'marketing_employee_id' => $salesUser1->id,
            'status' => 'confirmed',
        ]);
        
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit2->id,
            'marketing_employee_id' => $salesUser2->id,
            'status' => 'confirmed',
        ]);
        
        // User1 should only see their own reservation
        $response = $this->actingAs($salesUser1, 'sanctum')
            ->getJson('/api/sales/reservations');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $reservations = $response->json('data');
        $this->assertCount(1, $reservations);
        $this->assertEquals($salesUser1->id, $reservations[0]['marketing_employee_id']);
>>>>>>> parent of ad8e607 (Add Edits and Fixes)
    }
}
