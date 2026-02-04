<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\DeveloperMarketingPlan;
use App\Models\EmployeeMarketingPlan;
use App\Models\MarketingTask;
use App\Models\Lead;
use App\Models\MarketingSetting;

/**
 * Comprehensive test coverage for Marketing module access control
 * Tests all marketing-related routes for proper authorization
 */
class MarketingAccessTest extends BasePermissionTestCase
{
    private Contract $contract;
    private MarketingProject $marketingProject;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->contract = $this->createContractWithUnits(5);
        $this->marketingProject = MarketingProject::factory()->create([
            'contract_id' => $this->contract->id,
        ]);
    }

    #[Test]
    public function marketing_dashboard_requires_authentication()
    {
        $this->assertRouteRequiresAuth('GET', '/api/marketing/dashboard');
    }

    #[Test]
    public function marketing_dashboard_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/marketing/dashboard');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function marketing_dashboard_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/marketing/dashboard');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function marketing_dashboard_forbidden_for_non_marketing_users()
    {
        $users = [
            $this->createSalesStaff(),
            $this->createHRStaff(),
            $this->createEditor(),
            $this->createDeveloper(),
        ];

        foreach ($users as $user) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/marketing/dashboard');
            
            $response->assertStatus(403);
        }
    }

    #[Test]
    public function marketing_projects_list_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/marketing/projects');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function marketing_project_details_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/projects/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function calculate_budget_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/marketing/projects/calculate-budget', [
                'contract_id' => $this->contract->id,
                'duration_months' => 6,
                'budget_percentage' => 5,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function calculate_budget_forbidden_for_non_marketing_users()
    {
        $sales = $this->createSalesStaff();
        
        $response = $this->actingAs($sales, 'sanctum')
            ->postJson('/api/marketing/projects/calculate-budget', [
                'contract_id' => $this->contract->id,
                'duration_months' => 6,
            ]);
        
        $response->assertStatus(403);
    }

    #[Test]
    public function view_developer_plan_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/developer-plans/{$this->contract->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_developer_plan_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/marketing/developer-plans', [
                'contract_id' => $this->contract->id,
                'budget' => 100000,
                'duration_months' => 6,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_employee_plans_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/employee-plans/project/{$this->marketingProject->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_employee_plan_details_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $plan = EmployeeMarketingPlan::factory()->create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $marketing->id,
        ]);
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/employee-plans/{$plan->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_employee_plan_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/marketing/employee-plans', [
                'marketing_project_id' => $this->marketingProject->id,
                'user_id' => $marketing->id,
                'allocated_budget' => 10000,
                'duration_days' => 30,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function auto_generate_employee_plans_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/marketing/employee-plans/auto-generate', [
                'marketing_project_id' => $this->marketingProject->id,
                'team_size' => 3,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function calculate_expected_sales_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/expected-sales/{$this->marketingProject->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_conversion_rate_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->putJson('/api/marketing/settings/conversion-rate', [
                'conversion_rate' => 0.05,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_tasks_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/marketing/tasks');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_task_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/marketing/tasks', [
                'marketing_project_id' => $this->marketingProject->id,
                'title' => 'Test Task',
                'description' => 'Test Description',
                'assigned_to' => $marketing->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_task_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $task = MarketingTask::factory()->create([
            'marketer_id' => $marketing->id,
            'contract_id' => $this->contract->id,
        ]);
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->putJson("/api/marketing/tasks/{$task->id}", [
                'title' => 'Updated Task',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_task_status_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $task = MarketingTask::factory()->create([
            'marketer_id' => $marketing->id,
            'contract_id' => $this->contract->id,
        ]);
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->patchJson("/api/marketing/tasks/{$task->id}/status", [
                'status' => 'completed',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function assign_team_to_project_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson("/api/marketing/projects/{$this->marketingProject->id}/team", [
                'team_members' => [$marketing->id],
                'team_leader' => $marketing->id,
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function get_project_team_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/projects/{$this->marketingProject->id}/team");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function recommend_employee_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/projects/{$this->marketingProject->id}/recommend-employee");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_leads_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/marketing/leads');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function create_lead_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->postJson('/api/marketing/leads', [
                'project_id' => $this->marketingProject->contract_id,
                'name' => 'Test Lead',
                'contact_info' => '1234567890',
                'source' => 'website',
                'status' => 'new',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_lead_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $lead = Lead::factory()->create([
            'project_id' => $this->marketingProject->contract_id,
            'assigned_to' => $marketing->id,
        ]);
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->putJson("/api/marketing/leads/{$lead->id}", [
                'status' => 'qualified',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_project_performance_report_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/reports/project/{$this->marketingProject->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_budget_report_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/marketing/reports/budget');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_expected_bookings_report_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/marketing/reports/expected-bookings');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_employee_performance_report_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/reports/employee/{$marketing->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function export_plan_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $plan = EmployeeMarketingPlan::factory()->create([
            'marketing_project_id' => $this->marketingProject->id,
            'user_id' => $marketing->id,
        ]);
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson("/api/marketing/reports/export/{$plan->id}");
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function view_settings_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/marketing/settings');
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_settings_accessible_by_marketing_staff()
    {
        $marketing = $this->createMarketingStaff();
        
        MarketingSetting::create([
            'key' => 'conversion_rate',
            'value' => '0.03',
        ]);
        
        $response = $this->actingAs($marketing, 'sanctum')
            ->putJson('/api/marketing/settings/conversion_rate', [
                'value' => '0.05',
            ]);
        
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function marketing_routes_forbidden_for_sales_staff()
    {
        $sales = $this->createSalesStaff();
        
        $routes = [
            ['GET', '/api/marketing/dashboard'],
            ['GET', '/api/marketing/projects'],
            ['GET', '/api/marketing/tasks'],
            ['GET', '/api/marketing/leads'],
            ['GET', '/api/marketing/reports/budget'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($sales, 'sanctum')
                ->json($method, $uri);
            
            $response->assertStatus(403);
        }
    }

    #[Test]
    public function marketing_routes_forbidden_for_hr_staff()
    {
        $hr = $this->createHRStaff();
        
        $routes = [
            ['GET', '/api/marketing/dashboard'],
            ['GET', '/api/marketing/projects'],
            ['POST', '/api/marketing/developer-plans'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($hr, 'sanctum')
                ->json($method, $uri);
            
            $response->assertStatus(403);
        }
    }

    #[Test]
    public function marketing_permissions_are_correctly_assigned()
    {
        $marketing = $this->createMarketingStaff();
        
        $expectedPermissions = [
            'marketing.dashboard.view',
            'marketing.projects.view',
            'marketing.plans.create',
            'marketing.budgets.manage',
            'marketing.tasks.view',
            'marketing.tasks.confirm',
            'marketing.reports.view',
        ];
        
        $this->assertUserHasAllPermissions($marketing, $expectedPermissions);
    }

    #[Test]
    public function marketing_staff_does_not_have_sales_permissions()
    {
        $marketing = $this->createMarketingStaff();
        
        $salesPermissions = [
            'sales.dashboard.view',
            'sales.reservations.create',
            'sales.team.manage',
        ];
        
        $this->assertUserDoesNotHavePermissions($marketing, $salesPermissions);
    }

    #[Test]
    public function marketing_staff_does_not_have_hr_permissions()
    {
        $marketing = $this->createMarketingStaff();
        
        $hrPermissions = [
            'hr.employees.manage',
            'hr.users.create',
            'hr.performance.view',
        ];
        
        $this->assertUserDoesNotHavePermissions($marketing, $hrPermissions);
    }

    #[Test]
    public function marketing_staff_has_exclusive_project_permissions()
    {
        $marketing = $this->createMarketingStaff();
        
        $exclusivePermissions = [
            'exclusive_projects.request',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];
        
        $this->assertUserHasAllPermissions($marketing, $exclusivePermissions);
    }

    #[Test]
    public function marketing_staff_cannot_approve_exclusive_projects()
    {
        $marketing = $this->createMarketingStaff();
        
        $this->assertUserDoesNotHavePermissions($marketing, [
            'exclusive_projects.approve',
        ]);
    }
}
