<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\TestsWithPermissions;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base test case for permission and authorization testing
 * Provides comprehensive helper methods for role-based testing
 */
abstract class BasePermissionTestCase extends TestCase
{
    use RefreshDatabase, TestsWithPermissions;

    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Seed roles and permissions
        $this->seedRolesAndPermissions();
    }

    /**
     * Seed all roles and permissions from config
     */
    protected function seedRolesAndPermissions(): void
    {
        // Create all permissions from config
        $definitions = config('ai_capabilities.definitions', []);
        foreach ($definitions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Create roles with their permissions
        $roleMap = config('ai_capabilities.bootstrap_role_map', []);
        foreach ($roleMap as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }

    /**
     * Create a user with a specific type and manager status
     */
    protected function createUserWithType(string $type, bool $isManager = false, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'type' => $type,
            'is_manager' => $isManager,
        ], $attributes));
        
        $user->syncRolesFromType();
        
        return $user->fresh();
    }

    /**
     * Create an admin user
     */
    protected function createAdmin(array $attributes = []): User
    {
        return $this->createUserWithType('admin', false, $attributes);
    }

    /**
     * Create a sales staff user
     */
    protected function createSalesStaff(array $attributes = []): User
    {
        return $this->createUserWithType('sales', false, $attributes);
    }

    /**
     * Create a sales leader user
     */
    protected function createSalesLeader(array $attributes = []): User
    {
        return $this->createUserWithType('sales', true, $attributes);
    }

    /**
     * Create a marketing staff user
     */
    protected function createMarketingStaff(array $attributes = []): User
    {
        return $this->createUserWithType('marketing', false, $attributes);
    }

    /**
     * Create a project management staff user
     */
    protected function createProjectManagementStaff(array $attributes = []): User
    {
        return $this->createUserWithType('project_management', false, $attributes);
    }

    /**
     * Create a project management manager user
     */
    protected function createProjectManagementManager(array $attributes = []): User
    {
        return $this->createUserWithType('project_management', true, $attributes);
    }

    /**
     * Create an HR staff user
     */
    protected function createHRStaff(array $attributes = []): User
    {
        return $this->createUserWithType('hr', false, $attributes);
    }

    /**
     * Create an editor user
     */
    protected function createEditor(array $attributes = []): User
    {
        return $this->createUserWithType('editor', false, $attributes);
    }

    /**
     * Create a developer user
     */
    protected function createDeveloper(array $attributes = []): User
    {
        return $this->createUserWithType('developer', false, $attributes);
    }

    /**
     * Create a default user
     */
    protected function createDefaultUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'type' => 'user',
            'is_manager' => false,
        ], $attributes));
        
        // Manually assign default role if it exists
        if (\Spatie\Permission\Models\Role::where('name', 'default')->exists()) {
            $user->assignRole('default');
        }
        
        return $user->fresh();
    }

    /**
     * Create a contract with units
     */
    protected function createContractWithUnits(int $unitCount = 3, array $contractAttributes = []): Contract
    {
        $contract = Contract::factory()->create($contractAttributes);
        
        // Create second party data for the contract
        $secondPartyData = \App\Models\SecondPartyData::factory()->create([
            'contract_id' => $contract->id,
        ]);
        
        // Create units linked to the second party data
        ContractUnit::factory()->count($unitCount)->create([
            'second_party_data_id' => $secondPartyData->id,
        ]);
        
        return $contract->fresh();
    }

    /**
     * Create a team
     */
    protected function createTeam(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Test Team',
            'description' => 'Test team description',
        ], $attributes));
    }

    /**
     * Assert that a route is accessible for a user
     */
    protected function assertRouteAccessible(User $user, string $method, string $uri, array $data = []): void
    {
        $response = $this->actingAs($user, 'sanctum')->json($method, $uri, $data);
        
        $this->assertNotEquals(401, $response->status(), "Route {$method} {$uri} returned 401 Unauthenticated");
        $this->assertNotEquals(403, $response->status(), "Route {$method} {$uri} returned 403 Forbidden");
    }

    /**
     * Assert that a route is forbidden for a user (403)
     */
    protected function assertRouteForbidden(User $user, string $method, string $uri, array $data = []): void
    {
        $response = $this->actingAs($user, 'sanctum')->json($method, $uri, $data);
        
        $response->assertStatus(403);
    }

    /**
     * Assert that a route requires authentication (401)
     */
    protected function assertRouteRequiresAuth(string $method, string $uri, array $data = []): void
    {
        $response = $this->json($method, $uri, $data);
        
        $response->assertStatus(401);
    }

    /**
     * Assert user has all specified permissions
     */
    protected function assertUserHasAllPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->assertTrue(
                $user->hasPermissionTo($permission),
                "User does not have permission: {$permission}"
            );
        }
    }

    /**
     * Assert user does not have any of the specified permissions
     */
    protected function assertUserDoesNotHavePermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->assertFalse(
                $user->hasPermissionTo($permission),
                "User has permission: {$permission} (should not have it)"
            );
        }
    }

    /**
     * Assert user has effective permission (including dynamic permissions)
     */
    protected function assertUserHasEffectivePermission(User $user, string $permission): void
    {
        $this->assertTrue(
            $user->hasEffectivePermission($permission),
            "User does not have effective permission: {$permission}"
        );
    }

    /**
     * Assert user does not have effective permission
     */
    protected function assertUserDoesNotHaveEffectivePermission(User $user, string $permission): void
    {
        $this->assertFalse(
            $user->hasEffectivePermission($permission),
            "User has effective permission: {$permission} (should not have it)"
        );
    }

    /**
     * Get all permissions for a role from config
     */
    protected function getRolePermissions(string $roleName): array
    {
        $roleMap = config('ai_capabilities.bootstrap_role_map', []);
        return $roleMap[$roleName] ?? [];
    }

    /**
     * Assert role has all expected permissions
     */
    protected function assertRoleHasPermissions(string $roleName, array $expectedPermissions): void
    {
        $role = Role::findByName($roleName, 'web');
        
        foreach ($expectedPermissions as $permission) {
            $this->assertTrue(
                $role->hasPermissionTo($permission),
                "Role {$roleName} does not have permission: {$permission}"
            );
        }
    }

    /**
     * Assert role does not have any of the specified permissions
     */
    protected function assertRoleDoesNotHavePermissions(string $roleName, array $permissions): void
    {
        $role = Role::findByName($roleName, 'web');
        
        foreach ($permissions as $permission) {
            $this->assertFalse(
                $role->hasPermissionTo($permission),
                "Role {$roleName} has permission: {$permission} (should not have it)"
            );
        }
    }

    /**
     * Test route access for multiple users
     * 
     * @param array $usersWithAccess Array of users who should have access
     * @param array $usersWithoutAccess Array of users who should not have access
     * @param string $method HTTP method
     * @param string $uri Route URI
     * @param array $data Request data
     */
    protected function testRouteAccessForUsers(
        array $usersWithAccess,
        array $usersWithoutAccess,
        string $method,
        string $uri,
        array $data = []
    ): void {
        // Test users who should have access
        foreach ($usersWithAccess as $user) {
            $this->assertRouteAccessible($user, $method, $uri, $data);
        }

        // Test users who should not have access
        foreach ($usersWithoutAccess as $user) {
            $this->assertRouteForbidden($user, $method, $uri, $data);
        }

        // Test unauthenticated access
        $this->assertRouteRequiresAuth($method, $uri, $data);
    }

    /**
     * Create all user types for comprehensive testing
     */
    protected function createAllUserTypes(): array
    {
        return [
            'admin' => $this->createAdmin(),
            'sales' => $this->createSalesStaff(),
            'sales_leader' => $this->createSalesLeader(),
            'marketing' => $this->createMarketingStaff(),
            'project_management' => $this->createProjectManagementStaff(),
            'project_management_manager' => $this->createProjectManagementManager(),
            'hr' => $this->createHRStaff(),
            'editor' => $this->createEditor(),
            'developer' => $this->createDeveloper(),
            'default' => $this->createDefaultUser(),
        ];
    }

    /**
     * Get expected permissions for a user type
     */
    protected function getExpectedPermissionsForType(string $type, bool $isManager = false): array
    {
        if ($type === 'sales' && $isManager) {
            return $this->getRolePermissions('sales_leader');
        }
        
        return $this->getRolePermissions($type);
    }

    /**
     * Assert JSON response has unauthorized message
     */
    protected function assertUnauthorizedResponse($response): void
    {
        $response->assertStatus(403);
        $response->assertJsonStructure(['success', 'message']);
        $response->assertJson(['success' => false]);
    }

    /**
     * Assert JSON response is successful
     */
    protected function assertSuccessResponse($response): void
    {
        $this->assertContains($response->status(), [200, 201]);
    }
}
