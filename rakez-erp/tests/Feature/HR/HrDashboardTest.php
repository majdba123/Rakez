<?php

namespace Tests\Feature\HR;

use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;
    protected User $regularUser;

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

        // Create regular user
        $this->regularUser = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
    }

    public function test_hr_user_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'kpis' => [
                        'avg_monthly_sales_per_employee',
                        'avg_monthly_team_sales',
                        'active_employees_count',
                        'avg_target_achievement_rate',
                    ],
                    'employees_by_department',
                ],
            ]);
    }

    public function test_non_hr_user_cannot_access_dashboard(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/hr/dashboard');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/hr/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_returns_correct_employee_count(): void
    {
        // Create additional active users
        User::factory()->count(5)->create(['is_active' => true]);
        User::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should include all active users (hrUser + regularUser + 5 new = 7)
        $this->assertEquals(7, $data['kpis']['active_employees_count']);
    }

    public function test_dashboard_accepts_year_and_month_filters(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/dashboard?year=2025&month=6');

        $response->assertStatus(200)
            ->assertJsonPath('meta.period.year', 2025)
            ->assertJsonPath('meta.period.month', 6);
    }

    public function test_hr_user_can_refresh_dashboard_cache(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->postJson('/api/hr/dashboard/refresh');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}

