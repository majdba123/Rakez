<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class EmployeeRoleTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $team;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::firstOrCreate(['name' => 'employees.manage', 'guard_name' => 'web']);

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $salesRole = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $marketingRole = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => 'web']);

        // Assign permissions to admin role
        $adminRole->givePermissionTo('employees.manage');

        // Create admin user
        $this->admin = User::factory()->create([
            'type' => 'admin',
            'email' => 'admin@test.com',
        ]);
        $this->admin->assignRole('admin');

        // Create a team for testing
        $this->team = Team::factory()->create();
    }

    /** @test */
    public function admin_can_list_all_roles()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/employees/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name']
                ]
            ]);

        $roles = $response->json('data');
        $this->assertGreaterThan(0, count($roles));
    }

    /** @test */
    public function non_admin_cannot_list_roles()
    {
        $user = User::factory()->create(['type' => 'sales']);
        $user->assignRole('sales');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/employees/roles');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_add_employee_with_specific_role()
    {
        $employeeData = [
            'name' => 'Test Employee',
            'email' => 'employee@test.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'type' => 5, // sales type
            'role' => 'sales',
            'team' => $this->team->id,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/employees/add_employee', $employeeData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
                'message'
            ]);

        $user = User::where('email', 'employee@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('sales'));
    }

    /** @test */
    public function admin_can_add_employee_without_role_and_it_syncs_from_type()
    {
        $employeeData = [
            'name' => 'Test Employee 2',
            'email' => 'employee2@test.com',
            'phone' => '1234567891',
            'password' => 'password123',
            'type' => 5, // sales type
            'team' => $this->team->id,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/employees/add_employee', $employeeData);

        $response->assertStatus(201);

        $user = User::where('email', 'employee2@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('sales'));
    }

    /** @test */
    public function validation_fails_when_role_does_not_exist()
    {
        $employeeData = [
            'name' => 'Test Employee',
            'email' => 'employee@test.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'type' => 5,
            'role' => 'nonexistent_role',
            'team' => $this->team->id,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/employees/add_employee', $employeeData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /** @test */
    public function admin_can_update_employee_role()
    {
        // Create an employee with sales role
        $employee = User::factory()->create([
            'type' => 'sales',
            'email' => 'employee@test.com',
        ]);
        $employee->assignRole('sales');

        $this->assertTrue($employee->hasRole('sales'));

        // Update to marketing role
        $updateData = [
            'role' => 'marketing',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/employees/update_employee/{$employee->id}", $updateData);

        $response->assertStatus(200);

        $employee->refresh();
        $this->assertTrue($employee->hasRole('marketing'));
        $this->assertFalse($employee->hasRole('sales'));
    }

    /** @test */
    public function admin_can_update_employee_without_role_and_it_syncs_from_type()
    {
        // Create an employee
        $employee = User::factory()->create([
            'type' => 'sales',
            'email' => 'employee@test.com',
            'is_manager' => false,
        ]);
        $employee->assignRole('sales');

        // Update to sales leader by changing is_manager
        $updateData = [
            'is_manager' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/employees/update_employee/{$employee->id}", $updateData);

        $response->assertStatus(200);

        $employee->refresh();
        $this->assertTrue($employee->hasRole('sales_leader'));
    }

    /** @test */
    public function validation_fails_when_updating_with_nonexistent_role()
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'email' => 'employee@test.com',
        ]);
        $employee->assignRole('sales');

        $updateData = [
            'role' => 'invalid_role',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/employees/update_employee/{$employee->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /** @test */
    public function admin_can_assign_different_role_than_type_suggests()
    {
        // Create employee with sales type but marketing role
        $employeeData = [
            'name' => 'Cross Role Employee',
            'email' => 'crossrole@test.com',
            'phone' => '1234567892',
            'password' => 'password123',
            'type' => 5, // sales type
            'role' => 'marketing', // but marketing role
            'team' => $this->team->id,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/employees/add_employee', $employeeData);

        $response->assertStatus(201);

        $user = User::where('email', 'crossrole@test.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('sales', $user->type);
        $this->assertTrue($user->hasRole('marketing'));
        $this->assertFalse($user->hasRole('sales'));
    }

    /** @test */
    public function roles_endpoint_returns_all_available_roles()
    {
        // Create additional roles
        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/employees/roles');

        $response->assertStatus(200);

        $roles = $response->json('data');
        $roleNames = array_column($roles, 'name');

        $this->assertContains('admin', $roleNames);
        $this->assertContains('sales', $roleNames);
        $this->assertContains('marketing', $roleNames);
        $this->assertContains('editor', $roleNames);
        $this->assertContains('hr', $roleNames);
    }
}
