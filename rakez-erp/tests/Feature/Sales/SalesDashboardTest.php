<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser;
    protected User $teamMember;
    protected User $otherTeamUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // Create sales users
        $this->salesUser = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Alpha',
        ]);
        $this->salesUser->assignRole('sales');

        $this->teamMember = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Alpha',
        ]);
        $this->teamMember->assignRole('sales');

        $this->otherTeamUser = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Beta',
        ]);
        $this->otherTeamUser->assignRole('sales');
    }

    public function test_dashboard_returns_kpi_counts()
    {
        // Create test data
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit1 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        $unit2 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 600000,
        ]);

        // Create reservations
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit1->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/dashboard?scope=me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reserved_units',
                    'available_units',
                    'projects_under_marketing',
                    'confirmed_reservations',
                    'negotiation_reservations',
                    'percent_confirmed',
                ],
            ]);
    }

    public function test_dashboard_scope_me_filters_by_employee()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit1 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        $unit2 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 600000,
        ]);

        // Create reservations for different users
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit1->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit2->id,
            'marketing_employee_id' => $this->teamMember->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/dashboard?scope=me');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only count the salesUser's reservation
        $this->assertEquals(1, $data['reserved_units']);
        $this->assertEquals(1, $data['confirmed_reservations']);
    }

    public function test_dashboard_scope_team_filters_by_team()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit1 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        $unit2 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 600000,
        ]);

        $unit3 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 700000,
        ]);

        // Team Alpha reservations
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit1->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
        ]);

        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit2->id,
            'marketing_employee_id' => $this->teamMember->id,
            'status' => 'under_negotiation',
        ]);

        // Team Beta reservation
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit3->id,
            'marketing_employee_id' => $this->otherTeamUser->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/dashboard?scope=team');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should count both Team Alpha reservations
        $this->assertEquals(2, $data['reserved_units']);
        $this->assertEquals(1, $data['confirmed_reservations']);
        $this->assertEquals(1, $data['negotiation_reservations']);
    }

    public function test_dashboard_calculates_percent_confirmed_correctly()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $units = ContractUnit::factory()->count(5)->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        // Create 3 confirmed and 2 negotiation reservations
        foreach ($units->take(3) as $unit) {
            SalesReservation::factory()->create([
                'contract_id' => $contract->id,
                'contract_unit_id' => $unit->id,
                'marketing_employee_id' => $this->salesUser->id,
                'status' => 'confirmed',
            ]);
        }

        foreach ($units->skip(3)->take(2) as $unit) {
            SalesReservation::factory()->create([
                'contract_id' => $contract->id,
                'contract_unit_id' => $unit->id,
                'marketing_employee_id' => $this->salesUser->id,
                'status' => 'under_negotiation',
            ]);
        }

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/dashboard?scope=me');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(3, $data['confirmed_reservations']);
        $this->assertEquals(2, $data['negotiation_reservations']);
        $this->assertEquals(60.0, $data['percent_confirmed']); // 3 / 5 * 100 = 60%
    }

    public function test_dashboard_date_range_filters_correctly()
    {
        $contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        
        $unit1 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 500000,
        ]);

        $unit2 = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'status' => 'available',
            'price' => 600000,
        ]);

        // Old reservation
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit1->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
            'created_at' => '2025-01-01',
        ]);

        // Recent reservation
        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit2->id,
            'marketing_employee_id' => $this->salesUser->id,
            'status' => 'confirmed',
            'created_at' => '2025-01-25',
        ]);

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->getJson('/api/sales/dashboard?scope=me&from=2025-01-20&to=2025-01-31');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only count the recent reservation
        $this->assertEquals(1, $data['confirmed_reservations']);
    }

    public function test_dashboard_requires_authentication()
    {
        $response = $this->getJson('/api/sales/dashboard');
        $response->assertStatus(401);
    }

    public function test_dashboard_requires_sales_role()
    {
        $user = User::factory()->create(['type' => 'developer']);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/sales/dashboard');
        
        $response->assertStatus(403);
    }
}
