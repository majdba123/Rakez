<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\MarketingTask;
use App\Models\SalesAttendanceSchedule;
use App\Models\SalesReservation;
use App\Models\SalesTarget;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesLeader;
    protected User $salesEmployee;
    protected User $nonSalesUser;
    protected Contract $contract;
    protected ContractUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // Admin user
        $this->admin = User::factory()->create(['type' => 'admin']);
        $this->admin->assignRole('admin');

        // Sales leader
        $this->salesLeader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team' => 'Team Alpha',
        ]);
        $this->salesLeader->assignRole('sales_leader');

        // Sales employee
        $this->salesEmployee = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
            'team' => 'Team Alpha',
        ]);
        $this->salesEmployee->assignRole('sales');

        // Non-sales user
        $this->nonSalesUser = User::factory()->create(['type' => 'developer']);

        // Test data
        $this->contract = Contract::factory()->create(['status' => 'ready']);
        $secondPartyData = SecondPartyData::factory()->create(['contract_id' => $this->contract->id]);
        $this->unit = ContractUnit::factory()->create([
            'second_party_data_id' => $secondPartyData->id,
            'price' => 500000,
        ]);

        // Assign leader to project for tests that require it
        \App\Models\SalesProjectAssignment::create([
            'leader_id' => $this->salesLeader->id,
            'contract_id' => $this->contract->id,
            'assigned_by' => $this->admin->id,
        ]);
    }

    // ===== Dashboard Authorization =====

    public function test_dashboard_requires_authentication()
    {
        $response = $this->getJson('/api/sales/dashboard');
        $response->assertStatus(401);
    }

    public function test_dashboard_requires_sales_role()
    {
        $response = $this->actingAs($this->nonSalesUser, 'sanctum')
            ->getJson('/api/sales/dashboard');
        $response->assertStatus(403);
    }

    public function test_sales_employee_can_access_dashboard()
    {
        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->getJson('/api/sales/dashboard');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_dashboard()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/sales/dashboard');
        $response->assertStatus(200);
    }

    // ===== Projects Authorization =====

    public function test_projects_list_requires_sales_role()
    {
        $response = $this->actingAs($this->nonSalesUser, 'sanctum')
            ->getJson('/api/sales/projects');
        $response->assertStatus(403);
    }

    public function test_sales_employee_can_list_projects()
    {
        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->getJson('/api/sales/projects');
        $response->assertStatus(200);
    }

    // ===== Reservations Authorization =====

    public function test_employee_can_view_own_reservations()
    {
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $this->salesEmployee->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->getJson('/api/sales/reservations?mine=1');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_view_other_users_reservation_details()
    {
        $otherEmployee = User::factory()->create(['type' => 'sales']);
        $otherEmployee->assignRole('sales');

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'marketing_employee_id' => $otherEmployee->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->postJson("/api/sales/reservations/{$reservation->id}/confirm");

        $response->assertStatus(403);
    }

    public function test_admin_can_access_all_reservations()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/sales/reservations');

        $response->assertStatus(200);
    }

    // ===== Targets Authorization =====

    public function test_leader_can_create_targets()
    {
        $data = [
            'marketer_id' => $this->salesEmployee->id,
            'contract_id' => $this->contract->id,
            'contract_unit_id' => $this->unit->id,
            'target_type' => 'reservation',
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-30',
        ];

        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->postJson('/api/sales/targets', $data);

        $response->assertStatus(201);
    }

    public function test_employee_cannot_create_targets()
    {
        $data = [
            'marketer_id' => $this->salesEmployee->id,
            'contract_id' => $this->contract->id,
            'target_type' => 'reservation',
            'start_date' => '2025-01-20',
            'end_date' => '2025-01-30',
        ];

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->postJson('/api/sales/targets', $data);

        $response->assertStatus(403);
    }

    public function test_marketer_can_update_own_target_status()
    {
        $target = SalesTarget::factory()->create([
            'leader_id' => $this->salesLeader->id,
            'marketer_id' => $this->salesEmployee->id,
            'contract_id' => $this->contract->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->patchJson("/api/sales/targets/{$target->id}", ['status' => 'in_progress']);

        $response->assertStatus(200);
    }

    public function test_marketer_cannot_update_other_marketers_target()
    {
        $otherMarketer = User::factory()->create(['type' => 'sales', 'team' => 'Team Alpha']);
        $otherMarketer->assignRole('sales');

        $target = SalesTarget::factory()->create([
            'leader_id' => $this->salesLeader->id,
            'marketer_id' => $otherMarketer->id,
            'contract_id' => $this->contract->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->patchJson("/api/sales/targets/{$target->id}", ['status' => 'completed']);

        $response->assertStatus(403);
    }

    // ===== Team Management Authorization (Leader Only) =====

    public function test_leader_can_access_team_projects()
    {
        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->getJson('/api/sales/team/projects');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_team_projects()
    {
        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->getJson('/api/sales/team/projects');

        $response->assertStatus(403);
    }

    public function test_leader_can_access_team_members()
    {
        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->getJson('/api/sales/team/members');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_team_members()
    {
        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->getJson('/api/sales/team/members');

        $response->assertStatus(403);
    }

    public function test_leader_can_update_emergency_contacts()
    {
        $data = [
            'emergency_contact_number' => '0509999999',
            'security_guard_number' => '0508888888',
        ];

        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->patchJson("/api/sales/projects/{$this->contract->id}/emergency-contacts", $data);

        $response->assertStatus(200);
    }

    public function test_employee_cannot_update_emergency_contacts()
    {
        $data = [
            'emergency_contact_number' => '0509999999',
            'security_guard_number' => '0508888888',
        ];

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->patchJson("/api/sales/projects/{$this->contract->id}/emergency-contacts", $data);

        $response->assertStatus(403);
    }

    // ===== Attendance Authorization =====

    public function test_employee_can_view_own_attendance()
    {
        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->getJson('/api/sales/attendance/my');

        $response->assertStatus(200);
    }

    public function test_leader_can_view_team_attendance()
    {
        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->getJson('/api/sales/attendance/team');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_view_team_attendance()
    {
        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->getJson('/api/sales/attendance/team');

        $response->assertStatus(403);
    }

    public function test_leader_can_create_attendance_schedule()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'user_id' => $this->salesEmployee->id,
            'schedule_date' => '2025-01-25',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];

        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->postJson('/api/sales/attendance/schedules', $data);

        $response->assertStatus(201);
    }

    public function test_employee_cannot_create_attendance_schedule()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'user_id' => $this->salesEmployee->id,
            'schedule_date' => '2025-01-25',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->postJson('/api/sales/attendance/schedules', $data);

        $response->assertStatus(403);
    }

    // ===== Marketing Tasks Authorization =====

    public function test_leader_can_create_marketing_tasks()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'task_name' => 'Test task',
            'marketer_id' => $this->salesEmployee->id,
            'participating_marketers_count' => 4,
        ];

        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->postJson('/api/sales/marketing-tasks', $data);

        $response->assertStatus(201);
    }

    public function test_employee_cannot_create_marketing_tasks()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'task_name' => 'Test task',
            'marketer_id' => $this->salesEmployee->id,
            'participating_marketers_count' => 4,
        ];

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->postJson('/api/sales/marketing-tasks', $data);

        $response->assertStatus(403);
    }

    public function test_leader_can_update_marketing_task_status()
    {
        $task = MarketingTask::factory()->create([
            'contract_id' => $this->contract->id,
            'marketer_id' => $this->salesEmployee->id,
            'created_by' => $this->salesLeader->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->patchJson("/api/sales/marketing-tasks/{$task->id}", ['status' => 'in_progress']);

        $response->assertStatus(200);
    }

    public function test_employee_cannot_update_marketing_task_status()
    {
        $task = MarketingTask::factory()->create([
            'contract_id' => $this->contract->id,
            'marketer_id' => $this->salesEmployee->id,
            'created_by' => $this->salesLeader->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->salesEmployee, 'sanctum')
            ->patchJson("/api/sales/marketing-tasks/{$task->id}", ['status' => 'completed']);

        $response->assertStatus(403);
    }

    // ===== Admin Endpoints =====

    public function test_admin_can_assign_projects_to_leaders()
    {
        $otherLeader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team' => 'Team Beta',
        ]);
        $otherLeader->assignRole('sales_leader');

        $otherContract = Contract::factory()->create(['status' => 'ready']);
        $data = [
            'leader_id' => $otherLeader->id,
            'contract_id' => $otherContract->id,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/sales/project-assignments', $data);

        $response->assertStatus(201);
    }

    public function test_sales_leader_cannot_assign_projects()
    {
        $data = [
            'leader_id' => $this->salesLeader->id,
            'contract_id' => $this->contract->id,
        ];

        $response = $this->actingAs($this->salesLeader, 'sanctum')
            ->postJson('/api/admin/sales/project-assignments', $data);

        $response->assertStatus(403);
    }

    public function test_non_sales_user_cannot_access_any_sales_endpoints()
    {
        $endpoints = [
            'GET' => [
                '/api/sales/dashboard',
                '/api/sales/projects',
                '/api/sales/reservations',
                '/api/sales/targets/my',
                '/api/sales/attendance/my',
            ],
            'POST' => [
                '/api/sales/reservations',
            ],
        ];

        foreach ($endpoints['GET'] as $endpoint) {
            $response = $this->actingAs($this->nonSalesUser, 'sanctum')
                ->getJson($endpoint);
            $response->assertStatus(403, "Failed for GET {$endpoint}");
        }

        foreach ($endpoints['POST'] as $endpoint) {
            $response = $this->actingAs($this->nonSalesUser, 'sanctum')
                ->postJson($endpoint, []);
            $response->assertStatus(403, "Failed for POST {$endpoint}");
        }
    }
}
