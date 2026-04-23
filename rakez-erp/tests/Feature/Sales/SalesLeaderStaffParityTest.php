<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLeaderStaffParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    private function createCompletedContractWithUnits(int $count = 2): Contract
    {
        $contract = Contract::factory()->create(['status' => 'completed']);
        SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        ContractUnit::factory()->count($count)->create([
            'contract_id' => $contract->id,
        ]);

        return $contract;
    }

    private function createSalesStaff(): User
    {
        $user = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
        ]);
        $user->assignRole('sales');

        return $user->fresh();
    }

    private function createSalesLeaderTypeUser(): User
    {
        $user = User::factory()->create([
            'type' => 'sales_leader',
            'is_manager' => true,
        ]);
        $user->assignRole('sales_leader');

        return $user->fresh();
    }

    public function test_units_search_returns_same_visibility_for_sales_staff_and_sales_leader_type_user(): void
    {
        $this->createCompletedContractWithUnits(3);

        $staff = $this->createSalesStaff();
        $leader = $this->createSalesLeaderTypeUser();

        $staffResponse = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/sales/units/search');
        $leaderResponse = $this->actingAs($leader, 'sanctum')
            ->getJson('/api/sales/units/search');

        $staffResponse->assertStatus(200)->assertJson(['success' => true]);
        $leaderResponse->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSame(
            $staffResponse->json('meta.total'),
            $leaderResponse->json('meta.total'),
            'Leader should have identical unit-search visibility as staff.'
        );
    }

    public function test_project_units_access_is_same_for_sales_staff_and_sales_leader_type_user(): void
    {
        $contract = $this->createCompletedContractWithUnits(2);

        $staff = $this->createSalesStaff();
        $leader = $this->createSalesLeaderTypeUser();

        $staffResponse = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}/units");
        $leaderResponse = $this->actingAs($leader, 'sanctum')
            ->getJson("/api/sales/projects/{$contract->id}/units");

        $staffResponse->assertStatus(200)->assertJson(['success' => true]);
        $leaderResponse->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSame(
            $staffResponse->json('meta.total'),
            $leaderResponse->json('meta.total'),
            'Leader should have identical project-units visibility as staff.'
        );
    }
}
