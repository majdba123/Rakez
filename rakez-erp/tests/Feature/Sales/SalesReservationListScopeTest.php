<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GET /api/sales/reservations visibility: sales leaders must see team/subordinate bookings, not only own rows.
 */
class SalesReservationListScopeTest extends TestCase
{
    use RefreshDatabase;

    private Contract $contract;

    private ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->contract = Contract::factory()->create(['status' => 'completed']);
        $this->unit = ContractUnit::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'available',
        ]);
    }

    #[Test]
    public function sales_leader_sees_team_member_reservations_not_only_own(): void
    {
        $team = Team::factory()->create();

        $leader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team_id' => $team->id,
        ]);
        $leader->assignRole('sales_leader');

        $rep = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
            'team_id' => $team->id,
        ]);
        $rep->assignRole('sales');

        $repReservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $rep->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($leader, 'sanctum')
            ->getJson('/api/sales/reservations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('reservation_id')->all();
        $this->assertContains($repReservation->id, $ids);
    }

    #[Test]
    public function regular_sales_sees_only_own_reservations(): void
    {
        $team = Team::factory()->create();

        $repA = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
        ]);
        $repA->assignRole('sales');

        $repB = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
        ]);
        $repB->assignRole('sales');

        $otherReservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $repB->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($repA, 'sanctum')
            ->getJson('/api/sales/reservations');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('reservation_id')->all();
        $this->assertNotContains($otherReservation->id, $ids);
    }

    #[Test]
    public function sales_leader_mine_filter_limits_to_own_rows(): void
    {
        $team = Team::factory()->create();

        $leader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team_id' => $team->id,
        ]);
        $leader->assignRole('sales_leader');

        $rep = User::factory()->create([
            'type' => 'sales',
            'team_id' => $team->id,
        ]);
        $rep->assignRole('sales');

        SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $rep->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($leader, 'sanctum')
            ->getJson('/api/sales/reservations?mine=1');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    #[Test]
    public function admin_sees_reservations_across_users(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->assignRole('admin');

        $sales = User::factory()->create(['type' => 'sales']);
        $sales->assignRole('sales');

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $sales->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/sales/reservations');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('reservation_id')->all();
        $this->assertContains($reservation->id, $ids);
    }
}
