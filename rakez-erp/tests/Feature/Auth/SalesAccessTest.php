<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SalesTarget;
use App\Models\SalesAttendanceSchedule;
use App\Models\SalesWaitingList;
use App\Models\SalesProjectAssignment;

/**
 * Comprehensive test coverage for Sales module access control
 * Tests all sales-related routes for proper authorization
 */
class SalesAccessTest extends BasePermissionTestCase
{
    private Contract $contract;
    private ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->contract = $this->createContractWithUnits(5);
        $this->unit = $this->contract->units()->first();
    }

    #[Test]
    public function sales_dashboard_requires_authentication()
    {
        $this->assertRouteRequiresAuth('GET', '/api/sales/dashboard');
    }

    #[Test]
    public function sales_dashboard_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/dashboard');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function sales_dashboard_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->getJson('/api/sales/dashboard');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function sales_dashboard_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/sales/dashboard');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function sales_dashboard_forbidden_for_non_sales_users()
    {
        $users = [
            $this->createMarketingStaff(),
            $this->createHRStaff(),
            $this->createEditor(),
            $this->createDeveloper(),
        ];

        foreach ($users as $user) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/sales/dashboard');
            
            $response->assertStatus(403);
        }
    }

    #[Test]
    public function sales_projects_list_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/projects');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function sales_project_details_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson("/api/sales/projects/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function sales_project_units_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson("/api/sales/projects/{$this->contract->id}/units");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function reservation_context_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson("/api/sales/units/{$this->unit->id}/reservation-context");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_reservation_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $data = [
            'contract_unit_id' => $this->unit->id,
            'client_name' => 'Test Client',
            'client_mobile' => '1234567890',
            'client_nationality' => 'Saudi',
            'reservation_type' => 'confirmed',
        ];
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson('/api/sales/reservations', $data);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_reservation_forbidden_for_non_sales_users()
    {
        $marketing = $this->createMarketingStaff();
        
        $data = [
            'contract_unit_id' => $this->unit->id,
            'client_name' => 'Test Client',
            'client_mobile' => '1234567890',
        ];
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/sales/reservations', $data);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function view_reservations_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/reservations');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function confirm_reservation_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $salesStaff->id,
            'status' => 'under_negotiation',
        ]);
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/confirm");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function cancel_reservation_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $salesStaff->id,
            'status' => 'under_negotiation',
        ]);
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/cancel");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function store_reservation_action_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $salesStaff->id,
        ]);
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/actions", [
                'action_type' => 'follow_up',
                'notes' => 'Test action',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function download_voucher_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $salesStaff->id,
            'status' => 'confirmed',
        ]);
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson("/api/sales/reservations/{$reservation->id}/voucher");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_my_targets_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/targets/my');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_target_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        $leader = $this->createSalesLeader();
        
        $target = SalesTarget::factory()->create([
            'marketer_id' => $salesStaff->id,
            'leader_id' => $leader->id,
            'contract_id' => $this->contract->id,
        ]);
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->patchJson("/api/sales/targets/{$target->id}", [
                'status' => 'in_progress',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_my_attendance_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/attendance/my');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_target_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson('/api/sales/targets', [
                'marketer_id' => $salesStaff->id,
                'contract_id' => $this->contract->id,
                'target_units' => 5,
            ]);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function create_target_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->postJson('/api/sales/targets', [
                'marketer_id' => $salesStaff->id,
                'contract_id' => $this->contract->id,
                'target_units' => 5,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_team_projects_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/team/projects');
        
        $response->assertStatus(403);
    }

    #[Test]
    public function view_team_projects_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->getJson('/api/sales/team/projects');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_team_members_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/team/members');
        
        $response->assertStatus(403);
    }

    #[Test]
    public function view_team_members_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->getJson('/api/sales/team/members');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_emergency_contacts_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->patchJson("/api/sales/projects/{$this->contract->id}/emergency-contacts", [
                'emergency_contacts' => ['John Doe: 1234567890'],
            ]);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function update_emergency_contacts_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->patchJson("/api/sales/projects/{$this->contract->id}/emergency-contacts", [
                'emergency_contacts' => ['John Doe: 1234567890'],
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_team_attendance_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/attendance/team');
        
        $response->assertStatus(403);
    }

    #[Test]
    public function view_team_attendance_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->getJson('/api/sales/attendance/team');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_attendance_schedule_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson('/api/sales/attendance/schedules', [
                'user_id' => $salesStaff->id,
                'date' => now()->toDateString(),
                'shift_start' => '09:00',
                'shift_end' => '17:00',
            ]);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function create_attendance_schedule_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->postJson('/api/sales/attendance/schedules', [
                'user_id' => $salesStaff->id,
                'date' => now()->toDateString(),
                'shift_start' => '09:00',
                'shift_end' => '17:00',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_waiting_list_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/waiting-list');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_waiting_list_by_unit_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson("/api/sales/waiting-list/unit/{$this->unit->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_waiting_list_entry_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson('/api/sales/waiting-list', [
                'contract_unit_id' => $this->unit->id,
                'client_name' => 'Test Client',
                'client_mobile' => '1234567890',
                'notes' => 'Test notes',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function convert_waiting_list_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $waitingEntry = SalesWaitingList::factory()->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $salesStaff->id,
            'status' => 'waiting',
        ]);
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson("/api/sales/waiting-list/{$waitingEntry->id}/convert");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function convert_waiting_list_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        $salesStaff = $this->createSalesStaff();
        
        $waitingEntry = SalesWaitingList::factory()->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $salesStaff->id,
            'status' => 'waiting',
        ]);
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->postJson("/api/sales/waiting-list/{$waitingEntry->id}/convert");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function cancel_waiting_list_entry_accessible_by_sales_staff()
    {
        $salesStaff = $this->createSalesStaff();
        
        $waitingEntry = SalesWaitingList::factory()->create([
            'contract_unit_id' => $this->unit->id,
            'sales_staff_id' => $salesStaff->id,
            'status' => 'waiting',
        ]);
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->deleteJson("/api/sales/waiting-list/{$waitingEntry->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function manage_marketing_tasks_requires_leader_permission()
    {
        $salesStaff = $this->createSalesStaff();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->getJson('/api/sales/tasks/projects');
        
        $response->assertStatus(403);
    }

    #[Test]
    public function manage_marketing_tasks_accessible_by_sales_leader()
    {
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($salesLeader, 'sanctum')
            ->getJson('/api/sales/tasks/projects');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function admin_can_assign_project_to_sales_team()
    {
        $admin = $this->createAdmin();
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/sales/project-assignments', [
                'contract_id' => $this->contract->id,
                'leader_id' => $salesLeader->id,
                'start_date' => now()->toDateString(),
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function sales_staff_cannot_assign_projects()
    {
        $salesStaff = $this->createSalesStaff();
        $salesLeader = $this->createSalesLeader();
        
        $response = $this->actingAs($salesStaff, 'sanctum')
            ->postJson('/api/admin/sales/project-assignments', [
                'contract_id' => $this->contract->id,
                'leader_id' => $salesLeader->id,
            ]);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function sales_permissions_are_correctly_assigned()
    {
        $salesStaff = $this->createSalesStaff();
        
        $expectedPermissions = [
            'sales.dashboard.view',
            'sales.projects.view',
            'sales.units.view',
            'sales.units.book',
            'sales.reservations.create',
            'sales.reservations.view',
            'sales.reservations.confirm',
            'sales.reservations.cancel',
            'sales.waiting_list.create',
            'sales.goals.view',
            'sales.targets.view',
            'sales.targets.update',
            'sales.attendance.view',
        ];
        
        $this->assertUserHasAllPermissions($salesStaff, $expectedPermissions);
    }

    #[Test]
    public function sales_leader_permissions_are_correctly_assigned()
    {
        $salesLeader = $this->createSalesLeader();
        
        $leaderPermissions = [
            'sales.waiting_list.convert',
            'sales.goals.create',
            'sales.team.manage',
            'sales.attendance.manage',
            'sales.tasks.manage',
        ];
        
        $this->assertUserHasAllPermissions($salesLeader, $leaderPermissions);
    }

    #[Test]
    public function sales_staff_does_not_have_leader_permissions()
    {
        $salesStaff = $this->createSalesStaff();
        
        $leaderOnlyPermissions = [
            'sales.waiting_list.convert',
            'sales.goals.create',
            'sales.team.manage',
            'sales.attendance.manage',
            'sales.tasks.manage',
        ];
        
        $this->assertUserDoesNotHavePermissions($salesStaff, $leaderOnlyPermissions);
    }
}
