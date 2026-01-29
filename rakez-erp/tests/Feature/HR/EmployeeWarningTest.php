<?php

namespace Tests\Feature\HR;

use App\Models\User;
use App\Models\EmployeeWarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeWarningTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Create HR permissions
        $permissions = [
            'hr.dashboard.view',
            'hr.teams.manage',
            'hr.employees.manage',
            'hr.performance.view',
            'hr.warnings.manage',
            'hr.contracts.manage',
            'hr.reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create HR role
        $hrRole = Role::firstOrCreate(['name' => 'HR']);
        $hrRole->syncPermissions($permissions);

        // Create HR user
        $this->hrUser = User::factory()->create([
            'type' => 'HR',
            'is_active' => true,
        ]);
        $this->hrUser->assignRole('HR');

        // Create employee
        $this->employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
    }

    public function test_hr_user_can_list_employee_warnings(): void
    {
        // Create some warnings
        EmployeeWarning::factory()->count(3)->create([
            'user_id' => $this->employee->id,
            'issued_by' => $this->hrUser->id,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson("/api/hr/users/{$this->employee->id}/warnings");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_hr_user_can_issue_warning(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->postJson("/api/hr/users/{$this->employee->id}/warnings", [
                'type' => 'performance',
                'reason' => 'Poor sales performance this month',
                'details' => 'Achievement rate below 30%',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'performance',
                    'reason' => 'Poor sales performance this month',
                ],
            ]);

        $this->assertDatabaseHas('employee_warnings', [
            'user_id' => $this->employee->id,
            'issued_by' => $this->hrUser->id,
            'type' => 'performance',
        ]);
    }

    public function test_hr_user_can_delete_warning(): void
    {
        $warning = EmployeeWarning::factory()->create([
            'user_id' => $this->employee->id,
            'issued_by' => $this->hrUser->id,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->deleteJson("/api/hr/warnings/{$warning->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('employee_warnings', ['id' => $warning->id]);
    }

    public function test_warnings_can_be_filtered_by_year(): void
    {
        EmployeeWarning::factory()->create([
            'user_id' => $this->employee->id,
            'warning_date' => '2025-06-15',
        ]);
        EmployeeWarning::factory()->create([
            'user_id' => $this->employee->id,
            'warning_date' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson("/api/hr/users/{$this->employee->id}/warnings?year=2026");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_warnings_can_be_filtered_by_type(): void
    {
        EmployeeWarning::factory()->create([
            'user_id' => $this->employee->id,
            'type' => 'performance',
        ]);
        EmployeeWarning::factory()->create([
            'user_id' => $this->employee->id,
            'type' => 'attendance',
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson("/api/hr/users/{$this->employee->id}/warnings?type=performance");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}

