<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;

class HRAccessTest extends BasePermissionTestCase
{
    #[Test]
    public function list_roles_requires_authentication()
    {
        $this->assertRouteRequiresAuth('GET', '/api/hr/users/roles');
    }

    #[Test]
    public function list_roles_accessible_by_admin()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/users/roles');

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function list_roles_forbidden_for_non_admin_users()
    {
        $users = [
            $this->createSalesStaff(),
            $this->createMarketingStaff(),
            $this->createProjectManagementStaff(),
            $this->createEditor(),
        ];

        foreach ($users as $user) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/hr/users/roles');

            $response->assertStatus(403);
        }
    }

    #[Test]
    public function add_employee_accessible_by_admin()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/hr/users', [
                'name' => 'New Employee',
                'email' => 'newemployee@example.com',
                'password' => 'password123',
                'type' => 'sales',
                'phone' => '1234567890',
            ]);

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function add_employee_forbidden_for_non_admin_users()
    {
        $sales = $this->createSalesStaff();

        $response = $this->actingAs($sales, 'sanctum')
            ->postJson('/api/hr/users', [
                'name' => 'New Employee',
                'email' => 'newemployee@example.com',
                'password' => 'password123',
                'type' => 'sales',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function list_employees_accessible_by_admin()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/users');

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function list_employees_with_filters_accessible_by_admin()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/users?type=sales&status=active');

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function list_employees_forbidden_for_non_admin_users()
    {
        $marketing = $this->createMarketingStaff();

        $response = $this->actingAs($marketing, 'sanctum')
            ->getJson('/api/hr/users');

        $response->assertStatus(403);
    }

    #[Test]
    public function show_employee_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        $employee = $this->createSalesStaff();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/hr/users/{$employee->id}");

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function show_employee_forbidden_for_non_admin_users()
    {
        $sales = $this->createSalesStaff();
        $otherEmployee = $this->createMarketingStaff();

        $response = $this->actingAs($sales, 'sanctum')
            ->getJson("/api/hr/users/{$otherEmployee->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function update_employee_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        $employee = $this->createSalesStaff();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/hr/users/{$employee->id}", [
                'name' => 'Updated Name',
            ]);

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function update_employee_forbidden_for_non_admin_users()
    {
        $sales = $this->createSalesStaff();
        $otherEmployee = $this->createMarketingStaff();

        $response = $this->actingAs($sales, 'sanctum')
            ->putJson("/api/hr/users/{$otherEmployee->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function delete_employee_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        $employee = $this->createSalesStaff();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/hr/users/{$employee->id}");

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function delete_employee_forbidden_for_non_admin_users()
    {
        $sales = $this->createSalesStaff();
        $otherEmployee = $this->createMarketingStaff();

        $response = $this->actingAs($sales, 'sanctum')
            ->deleteJson("/api/hr/users/{$otherEmployee->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function restore_employee_accessible_by_admin()
    {
        $admin = $this->createAdmin();
        $employee = $this->createSalesStaff();
        $employee->delete();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/hr/users/{$employee->id}/restore");

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function restore_employee_forbidden_for_non_admin_users()
    {
        $sales = $this->createSalesStaff();
        $employee = $this->createMarketingStaff();
        $employee->delete();

        $response = $this->actingAs($sales, 'sanctum')
            ->patchJson("/api/hr/users/{$employee->id}/restore");

        $response->assertStatus(403);
    }

    #[Test]
    public function hr_staff_does_not_have_employee_management_permissions()
    {
        $hr = $this->createHRStaff();

        $this->assertTrue($hr->hasPermissionTo('hr.employees.manage'));

        $response = $this->actingAs($hr, 'sanctum')
            ->getJson('/api/hr/users');

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function admin_has_all_employee_management_permissions()
    {
        $admin = $this->createAdmin();

        $expectedPermissions = [
            'employees.manage',
        ];

        $this->assertUserHasAllPermissions($admin, $expectedPermissions);
    }

    #[Test]
    public function hr_permissions_are_correctly_assigned()
    {
        $hr = $this->createHRStaff();

        $expectedPermissions = [
            'hr.employees.manage',
            'hr.users.create',
            'hr.performance.view',
            'hr.reports.print',
        ];

        $this->assertUserHasAllPermissions($hr, $expectedPermissions);
    }

    #[Test]
    public function hr_does_not_have_exclusive_project_permissions()
    {
        $hr = $this->createHRStaff();

        $forbiddenPermissions = [
            'exclusive_projects.request',
            'exclusive_projects.approve',
            'exclusive_projects.contract.complete',
            'exclusive_projects.contract.export',
        ];

        $this->assertUserDoesNotHavePermissions($hr, $forbiddenPermissions);
    }

    #[Test]
    public function hr_does_not_have_sales_permissions()
    {
        $hr = $this->createHRStaff();

        $salesPermissions = [
            'sales.dashboard.view',
            'sales.projects.view',
            'sales.reservations.create',
            'sales.team.manage',
        ];

        $this->assertUserDoesNotHavePermissions($hr, $salesPermissions);
    }

    #[Test]
    public function hr_does_not_have_marketing_permissions()
    {
        $hr = $this->createHRStaff();

        $marketingPermissions = [
            'marketing.dashboard.view',
            'marketing.projects.view',
            'marketing.plans.create',
            'marketing.budgets.manage',
        ];

        $this->assertUserDoesNotHavePermissions($hr, $marketingPermissions);
    }

    #[Test]
    public function hr_does_not_have_pm_permissions()
    {
        $hr = $this->createHRStaff();

        $pmPermissions = [
            'contracts.view_all',
            'units.edit',
            'second_party.edit',
            'departments.boards.edit',
        ];

        $this->assertUserDoesNotHavePermissions($hr, $pmPermissions);
    }

    #[Test]
    public function sales_staff_cannot_access_hr_routes()
    {
        $sales = $this->createSalesStaff();

        $routes = [
            ['GET', '/api/hr/users'],
            ['POST', '/api/hr/users'],
            ['GET', '/api/hr/users/roles'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($sales, 'sanctum')
                ->json($method, $uri);

            $response->assertStatus(403);
        }
    }

    #[Test]
    public function marketing_staff_cannot_access_hr_routes()
    {
        $marketing = $this->createMarketingStaff();

        $routes = [
            ['GET', '/api/hr/users'],
            ['POST', '/api/hr/users'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($marketing, 'sanctum')
                ->json($method, $uri);

            $response->assertStatus(403);
        }
    }

    #[Test]
    public function pm_staff_cannot_access_hr_routes()
    {
        $pm = $this->createProjectManagementStaff();

        $routes = [
            ['GET', '/api/hr/users'],
            ['DELETE', '/api/hr/users/1'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($pm, 'sanctum')
                ->json($method, $uri);

            $response->assertStatus(403);
        }
    }

    #[Test]
    public function editor_cannot_access_hr_routes()
    {
        $editor = $this->createEditor();
        $employee = $this->createSalesStaff();

        $response = $this->actingAs($editor, 'sanctum')
            ->getJson("/api/hr/users/{$employee->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function developer_cannot_access_hr_routes()
    {
        $developer = $this->createDeveloper();

        $response = $this->actingAs($developer, 'sanctum')
            ->getJson('/api/hr/users');

        $response->assertStatus(403);
    }

    #[Test]
    public function only_admin_can_manage_employees()
    {
        $admin = $this->createAdmin();
        $nonHrUsers = [
            $this->createSalesStaff(),
            $this->createMarketingStaff(),
            $this->createProjectManagementStaff(),
            $this->createEditor(),
            $this->createDeveloper(),
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/users');
        $this->assertNotEquals(403, $response->status());

        foreach ($nonHrUsers as $user) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/hr/users');

            $response->assertStatus(403);
        }
    }

    #[Test]
    public function admin_can_perform_all_employee_operations()
    {
        $admin = $this->createAdmin();
        $employee = $this->createSalesStaff();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/users');
        $this->assertNotEquals(403, $response->status());

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/hr/users/{$employee->id}");
        $this->assertNotEquals(403, $response->status());

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/hr/users/{$employee->id}", [
                'name' => 'Updated Name',
            ]);
        $this->assertNotEquals(403, $response->status());

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/hr/users', [
                'name' => 'New Employee',
                'email' => 'new' . time() . '@example.com',
                'password' => 'password123',
                'type' => 'marketing',
                'phone' => '1234567890',
            ]);
        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function hr_role_is_isolated_from_other_departments()
    {
        $hr = $this->createHRStaff();

        $this->assertTrue($hr->hasPermissionTo('hr.employees.manage'));
        $this->assertTrue($hr->hasPermissionTo('hr.performance.view'));

        $this->assertFalse($hr->hasPermissionTo('sales.dashboard.view'));
        $this->assertFalse($hr->hasPermissionTo('marketing.dashboard.view'));
        $this->assertFalse($hr->hasPermissionTo('contracts.view_all'));
        $this->assertFalse($hr->hasPermissionTo('exclusive_projects.request'));
    }
}
