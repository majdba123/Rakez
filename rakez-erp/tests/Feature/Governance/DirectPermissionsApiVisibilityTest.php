<?php

namespace Tests\Feature\Governance;

use Tests\TestCase;
use App\Models\User;
use App\Services\Governance\DirectPermissionGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DirectPermissionsApiVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure permission cache is cleared between tests
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    #[Test]
    public function direct_permission_assigned_in_filament_appears_in_login_response()
    {
        // Use a real governance permission that's defined in ai_capabilities
        $permissionName = 'contracts.view';
        
        // Ensure permission exists in Spatio
        Permission::firstOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web']
        );
        
        // Create role and user
        Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'type' => 'admin',
            'is_active' => true,
        ]);
        $user->assignRole('test_role');

        // Verify user has NO direct permissions initially
        $initialPermissions = $user->fresh()->getEffectivePermissions();
        $this->assertNotContains($permissionName, $initialPermissions,
            'User should not have permission before assignment');

        // Simulate Filament direct permission assignment via service
        $directPermService = app(DirectPermissionGovernanceService::class);
        $directPermService->sync($user, [$permissionName]);

        // Login via API
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Verify login succeeds
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'user',
            'permissions',
            'roles',
        ]);

        // CRITICAL CHECK: Direct permission must appear in login response
        $permissions = $response->json('permissions');
        $this->assertIsArray($permissions);
        $this->assertContains($permissionName, $permissions,
            'Direct permission assigned in Filament must appear in /api/login response');
    }

    #[Test]
    public function direct_permission_appears_in_current_user_endpoint()
    {
        // Setup
        $permissionName = 'contracts.create';
        Permission::firstOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web']
        );
        Role::firstOrCreate(['name' => 'endpoint_role', 'guard_name' => 'web']);
        
        $user = User::factory()->create([
            'email' => 'endpoint@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole('endpoint_role');

        // Assign direct permission
        $service = app(DirectPermissionGovernanceService::class);
        $service->sync($user, [$permissionName]);

        // Login to get token
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'endpoint@example.com',
            'password' => 'password',
        ]);
        $token = $loginResponse->json('access_token');

        // Get current user via API endpoint
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/user');

        $response->assertStatus(200);
        $permissions = $response->json('permissions');
        $this->assertContains($permissionName, $permissions,
            'Direct permission must appear in /api/user (current user) endpoint');
    }

    #[Test]
    public function removing_direct_permission_removes_it_from_payloads()
    {
        $permissionName = 'contracts.approve';
        Permission::firstOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web']
        );
        Role::firstOrCreate(['name' => 'revoke_role', 'guard_name' => 'web']);
        
        $user = User::factory()->create([
            'email' => 'revoke@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole('revoke_role');

        $service = app(DirectPermissionGovernanceService::class);
        
        // Assign permission
        $service->sync($user, [$permissionName]);
        
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'revoke@example.com',
            'password' => 'password',
        ]);
        $this->assertContains($permissionName, $loginResponse->json('permissions'),
            'Permission must be present after assignment');

        // Remove permission
        $service->sync($user, []); // Empty array removes all direct permissions
        
        // Re-login to get updated payload
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'revoke@example.com',
            'password' => 'password',
        ]);
        $permissions = $loginResponse->json('permissions');
        $this->assertNotContains($permissionName, $permissions,
            'Permission must be removed from payload after revocation');
    }

    #[Test]
    public function sensitive_fields_still_not_leaked_in_payloads()
    {
        $user = User::factory()->create([
            'email' => 'sensitive@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'sensitive@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        
        // Password must NOT be in response
        $this->assertArrayNotHasKey('password', $response->json('user'),
            'Password must never appear in login response');
        
        // Remember token must NOT be in response
        $this->assertArrayNotHasKey('remember_token', $response->json('user'),
            'Remember token must never appear in login response');
    }

    #[Test]
    public function role_based_permissions_still_appear_with_direct_permissions()
    {
        // Create permissions
        $rolePermission = 'contracts.view_all';
        $directPermission = 'contracts.view';
        
        Permission::firstOrCreate(
            ['name' => $rolePermission, 'guard_name' => 'web']
        );
        Permission::firstOrCreate(
            ['name' => $directPermission, 'guard_name' => 'web']
        );
        
        // Create role with permission
        $role = Role::firstOrCreate(['name' => 'role_with_perms', 'guard_name' => 'web']);
        $role->givePermissionTo($rolePermission);

        $user = User::factory()->create([
            'email' => 'both@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        // Add direct permission
        $service = app(DirectPermissionGovernanceService::class);
        $service->sync($user, [$directPermission]);

        // Login and verify both permissions present
        $response = $this->postJson('/api/login', [
            'email' => 'both@example.com',
            'password' => 'password',
        ]);

        $permissions = $response->json('permissions');
        $this->assertContains($rolePermission, $permissions,
            'Role-based permissions must still appear');
        $this->assertContains($directPermission, $permissions,
            'Direct permissions must also appear');
    }
}
