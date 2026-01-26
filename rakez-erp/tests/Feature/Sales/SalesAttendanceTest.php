<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\SalesAttendanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $leader;
    protected User $employee;
    protected Contract $contract;

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

        $this->employee = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
            'team' => 'Team Alpha',
        ]);
        $this->employee->assignRole('sales');

        $this->contract = Contract::factory()->create(['status' => 'ready']);
    }

    public function test_employee_can_view_their_schedules()
    {
        SalesAttendanceSchedule::factory()->count(3)->create([
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
        ]);

        // Create schedule for another employee
        $otherEmployee = User::factory()->create(['type' => 'sales', 'team' => 'Team Alpha']);
        SalesAttendanceSchedule::factory()->create([
            'contract_id' => $this->contract->id,
            'user_id' => $otherEmployee->id,
            'created_by' => $this->leader->id,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/sales/attendance/my');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_leader_can_create_schedule_for_team_member()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'schedule_date' => '2025-01-25',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/attendance/schedules', $data);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('sales_attendance_schedules', [
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
            'schedule_date' => '2025-01-25',
        ]);
    }

    public function test_employee_cannot_create_schedule()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'schedule_date' => '2025-01-25',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];

        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/sales/attendance/schedules', $data);

        $response->assertStatus(403);
    }

    public function test_leader_can_view_team_schedules()
    {
        SalesAttendanceSchedule::factory()->count(2)->create([
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
        ]);

        $anotherEmployee = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Alpha',
        ]);
        SalesAttendanceSchedule::factory()->create([
            'contract_id' => $this->contract->id,
            'user_id' => $anotherEmployee->id,
            'created_by' => $this->leader->id,
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->getJson('/api/sales/attendance/team');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_filter_team_schedules_by_user()
    {
        SalesAttendanceSchedule::factory()->count(2)->create([
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
        ]);

        $anotherEmployee = User::factory()->create([
            'type' => 'sales',
            'team' => 'Team Alpha',
        ]);
        SalesAttendanceSchedule::factory()->create([
            'contract_id' => $this->contract->id,
            'user_id' => $anotherEmployee->id,
            'created_by' => $this->leader->id,
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->getJson("/api/sales/attendance/team?user_id={$this->employee->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_filter_schedules_by_date_range()
    {
        SalesAttendanceSchedule::factory()->create([
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
            'schedule_date' => '2025-01-15',
        ]);

        SalesAttendanceSchedule::factory()->create([
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
            'schedule_date' => '2025-01-25',
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/sales/attendance/my?from=2025-01-20&to=2025-01-31');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_filter_team_schedules_by_project()
    {
        $anotherContract = Contract::factory()->create(['status' => 'ready']);

        SalesAttendanceSchedule::factory()->count(2)->create([
            'contract_id' => $this->contract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
        ]);

        SalesAttendanceSchedule::factory()->create([
            'contract_id' => $anotherContract->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->leader->id,
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->getJson("/api/sales/attendance/team?contract_id={$this->contract->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_create_schedule_validates_required_fields()
    {
        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/attendance/schedules', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'contract_id',
                'user_id',
                'schedule_date',
                'start_time',
                'end_time',
            ]);
    }

    public function test_leader_can_update_project_emergency_contacts()
    {
        $data = [
            'emergency_contact_number' => '0509999999',
            'security_guard_number' => '0508888888',
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->patchJson("/api/sales/projects/{$this->contract->id}/emergency-contacts", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('contracts', [
            'id' => $this->contract->id,
            'emergency_contact_number' => '0509999999',
            'security_guard_number' => '0508888888',
        ]);
    }

    public function test_employee_cannot_update_emergency_contacts()
    {
        $data = [
            'emergency_contact_number' => '0509999999',
            'security_guard_number' => '0508888888',
        ];

        $response = $this->actingAs($this->employee, 'sanctum')
            ->patchJson("/api/sales/projects/{$this->contract->id}/emergency-contacts", $data);

        $response->assertStatus(403);
    }
}
