<?php

namespace Tests\Feature\HR;

use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrUserTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create required roles first
        foreach (['admin', 'hr', 'sales', 'marketing', 'project_management', 'editor', 'credit', 'accounting', 'project_acquisition'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

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
        $hrRole = Role::firstOrCreate(['name' => 'hr']);
        $hrRole->syncPermissions($permissions);

        // Create HR user
        $this->hrUser = User::factory()->create([
            'type' => 'hr',
            'is_active' => true,
        ]);
        $this->hrUser->assignRole('hr');
    }

    public function test_hr_user_can_list_employees(): void
    {
        User::factory()->count(5)->create(['is_active' => true]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/users');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data',
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);
    }

    public function test_hr_user_can_filter_employees_by_status(): void
    {
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/users?is_active=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // All returned users should be active
        foreach ($data as $user) {
            $this->assertTrue($user['is_active']);
        }
    }

    public function test_hr_user_can_filter_employees_by_type(): void
    {
        User::factory()->create(['type' => 'sales']);
        User::factory()->create(['type' => 'marketing']);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/hr/users?type=sales');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $user) {
            $this->assertEquals('sales', $user['type']);
        }
    }

    public function test_hr_user_can_view_employee_profile(): void
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
            'nationality' => 'Saudi',
            'job_title' => 'Sales Representative',
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson("/api/hr/users/{$employee->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'nationality' => 'Saudi',
                    'job_title' => 'Sales Representative',
                ],
            ]);
    }

    public function test_hr_user_can_create_employee(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->postJson('/api/hr/users', [
                'name' => 'New Employee',
                'email' => 'newemployee@example.com',
                'phone' => '0501234567',
                'password' => 'password123',
                'type' => 5, // sales
                'nationality' => 'Saudi',
                'job_title' => 'Sales Rep',
                'department' => 'Sales',
                'salary' => 5000,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Employee',
                    'email' => 'newemployee@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newemployee@example.com',
            'nationality' => 'Saudi',
        ]);
    }

    public function test_hr_user_can_update_employee(): void
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->putJson("/api/hr/users/{$employee->id}", [
                'nationality' => 'Egyptian',
                'job_title' => 'Senior Sales Rep',
                'salary' => 7000,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'nationality' => 'Egyptian',
            'job_title' => 'Senior Sales Rep',
        ]);
    }

    public function test_hr_user_can_toggle_employee_status(): void
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->patchJson("/api/hr/users/{$employee->id}/status", [
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'is_active' => false]);
    }

    public function test_hr_user_can_delete_employee(): void
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->deleteJson("/api/hr/users/{$employee->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
    }

    public function test_hr_user_can_upload_employee_files(): void
    {
        $employee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);

        $cv = UploadedFile::fake()->create('cv.pdf', 1000, 'application/pdf');

        $response = $this->actingAs($this->hrUser)
            ->postJson("/api/hr/users/{$employee->id}/files", [
                'cv' => $cv,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $employee->refresh();
        $this->assertNotNull($employee->cv_path);
        Storage::disk('public')->assertExists($employee->cv_path);
    }
}

