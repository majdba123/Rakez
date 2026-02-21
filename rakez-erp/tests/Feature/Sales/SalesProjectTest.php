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
                    'readonly_project_unit_snapshot',
                    'flags',
                    'lookups' => [
                        'reservation_types',
                        'payment_methods',
                        'down_payment_statuses',
                        'purchase_mechanisms',
                        'nationalities',
                    ],
                ],
            ]);

        $response->assertJsonPath('data.flags.is_off_plan', false);
    }
}
