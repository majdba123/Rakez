<?php

namespace Tests\Feature\HR;

use App\Models\User;
use App\Models\Team;
use App\Models\EmployeeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;

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
    }

    public function test_hr_user_can_access_team_performance_report(): void
    {
        Team::factory()->count(3)->create();

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/reports/team-performance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'period' => ['year', 'month'],
                    'teams',
                    'totals',
                ],
            ]);
    }

    public function test_hr_user_can_access_marketer_performance_report(): void
    {
        User::factory()->count(5)->create(['type' => 'sales', 'is_active' => true]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/reports/marketer-performance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'marketers',
                    'totals',
                ],
            ]);
    }

    public function test_marketer_performance_report_can_be_filtered_by_team(): void
    {
        $team = Team::factory()->create();
        User::factory()->count(2)->create([
            'type' => 'sales',
            'is_active' => true,
            'team_id' => $team->id,
        ]);
        User::factory()->count(3)->create([
            'type' => 'sales',
            'is_active' => true,
            'team_id' => null,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson("/api/hr/reports/marketer-performance?team_id={$team->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return marketers from the specific team
        $this->assertCount(2, $data['marketers']);
    }

    public function test_hr_user_can_access_employee_count_report(): void
    {
        User::factory()->count(5)->create(['type' => 'sales', 'is_active' => true]);
        User::factory()->count(3)->create(['type' => 'marketing', 'is_active' => true]);
        User::factory()->count(2)->create(['type' => 'sales', 'is_active' => false]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/reports/employee-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'total_active',
                    'total_inactive',
                    'by_type',
                ],
            ]);
    }

    public function test_hr_user_can_access_expiring_contracts_report(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        
        // Contract expiring in 15 days
        EmployeeContract::factory()->create([
            'user_id' => $employee->id,
            'status' => 'active',
            'end_date' => now()->addDays(15),
        ]);

        // Contract expiring in 45 days (outside default 30-day window)
        EmployeeContract::factory()->create([
            'user_id' => $employee->id,
            'status' => 'active',
            'end_date' => now()->addDays(45),
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/reports/expiring-contracts');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'expiring_contracts',
                    'probation_ending',
                    'summary',
                ],
            ]);
    }

    public function test_expiring_contracts_report_respects_days_parameter(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        
        EmployeeContract::factory()->create([
            'user_id' => $employee->id,
            'status' => 'active',
            'end_date' => now()->addDays(45),
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/reports/expiring-contracts?days=60');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(60, $data['summary']['days_checked']);
        $this->assertCount(1, $data['expiring_contracts']);
    }
}

