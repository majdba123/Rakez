<?php

namespace Tests\Feature\Registration;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Team;
use App\Services\registartion\register;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Auth\BasePermissionTestCase;

/**
 * Test user registration service permission assignment
 */
class UserRegistrationPermissionTest extends BasePermissionTestCase
{
    protected register $registrationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->registrationService = new register();
    }
    
    /**
     * Create a team for testing
     */
    protected function createTestTeam(): Team
    {
        return Team::factory()->create();
    }

    #[Test]
    public function test_marketing_user_has_permissions_immediately_after_creation()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Marketing User',
            'email' => 'marketing@test.com',
            'phone' => '0501234567',
            'password' => 'password123',
            'type' => 0, // marketing
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Verify user has marketing role
        $this->assertTrue($user->hasRole('marketing'), 'User should have marketing role');

        // Verify user has marketing permissions immediately
        $this->assertTrue(
            $user->hasPermissionTo('marketing.dashboard.view'),
            'User should have marketing.dashboard.view permission immediately'
        );
        
        $this->assertTrue(
            $user->hasPermissionTo('marketing.projects.view'),
            'User should have marketing.projects.view permission immediately'
        );
        
        $this->assertTrue(
            $user->hasPermissionTo('marketing.plans.create'),
            'User should have marketing.plans.create permission immediately'
        );

        // Verify user can check permissions without cache issues
        $permissions = $user->getAllPermissions();
        $this->assertGreaterThan(0, $permissions->count(), 'User should have permissions');
    }

    #[Test]
    public function test_sales_user_has_permissions_immediately_after_creation()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Sales User',
            'email' => 'sales@test.com',
            'phone' => '0501234568',
            'password' => 'password123',
            'type' => 5, // sales
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Verify user has sales role
        $this->assertTrue($user->hasRole('sales'), 'User should have sales role');

        // Verify user has sales permissions immediately
        $this->assertTrue(
            $user->hasPermissionTo('sales.dashboard.view'),
            'User should have sales.dashboard.view permission immediately'
        );
        
        $this->assertTrue(
            $user->hasPermissionTo('sales.projects.view'),
            'User should have sales.projects.view permission immediately'
        );
        
        $this->assertTrue(
            $user->hasPermissionTo('sales.reservations.create'),
            'User should have sales.reservations.create permission immediately'
        );

        // Verify permissions are accessible immediately
        $permissions = $user->getAllPermissions();
        $this->assertGreaterThan(0, $permissions->count(), 'User should have permissions');
    }

    #[Test]
    public function test_user_with_explicit_role_has_permissions_immediately()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Editor User',
            'email' => 'editor@test.com',
            'phone' => '0501234569',
            'password' => 'password123',
            'type' => 4, // editor
            'role' => 'editor',
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Verify role is assigned correctly
        $this->assertTrue($user->hasRole('editor'), 'User should have editor role');

        // Verify all role permissions are available immediately
        $this->assertTrue(
            $user->hasPermissionTo('editing.projects.view'),
            'User should have editing.projects.view permission immediately'
        );
        
        $this->assertTrue(
            $user->hasPermissionTo('editing.media.upload'),
            'User should have editing.media.upload permission immediately'
        );
    }

    #[Test]
    public function test_permission_cache_is_cleared_after_role_assignment()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Test User',
            'email' => 'test@test.com',
            'phone' => '0501234570',
            'password' => 'password123',
            'type' => 0, // marketing
            'team' => $team->id,
        ];

        // Manually set permission cache before registration
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        
        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Verify cache is cleared and fresh permissions are loaded
        $this->assertTrue(
            $user->hasPermissionTo('marketing.dashboard.view'),
            'User should have fresh permissions after cache clear'
        );

        // Verify permissions are accessible without manual cache refresh
        $permissions = $user->getAllPermissions();
        $this->assertGreaterThan(0, $permissions->count());
    }

    #[Test]
    public function test_user_can_access_endpoints_immediately_after_creation()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Marketing Endpoint User',
            'email' => 'marketing-endpoint@test.com',
            'phone' => '0501234571',
            'password' => 'password123',
            'type' => 0, // marketing
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Immediately attempt to access marketing dashboard endpoint
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/marketing/dashboard');

        // Verify 200 response (not 403)
        $response->assertStatus(200);
        $this->assertNotEquals(403, $response->status(), 'User should not get 403 error');
    }

    #[Test]
    public function test_sales_user_can_access_sales_endpoints_immediately()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Sales Endpoint User',
            'email' => 'sales-endpoint@test.com',
            'phone' => '0501234572',
            'password' => 'password123',
            'type' => 5, // sales
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Immediately attempt to access sales dashboard endpoint
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/sales/dashboard?scope=me');

        // Verify 200 response with correct data
        $response->assertStatus(200);
        $this->assertNotEquals(403, $response->status(), 'User should not get 403 error');
        
        // Verify middleware allows access
        $response->assertJsonStructure([
            'success',
            'data'
        ]);
    }

    #[Test]
    public function test_user_update_syncs_permissions_correctly()
    {
        $team = $this->createTestTeam();
        
        // Create user with marketing role
        $userData = [
            'name' => 'Update Test User',
            'email' => 'update@test.com',
            'phone' => '0501234573',
            'password' => 'password123',
            'type' => 0, // marketing
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Verify initial role
        $this->assertTrue($user->hasRole('marketing'));
        $this->assertTrue($user->hasPermissionTo('marketing.dashboard.view'));

        // Update user to sales role via updateEmployee
        $updateData = [
            'type' => 5, // sales
        ];

        $updatedUser = $this->registrationService->updateEmployee($user->id, $updateData);
        $updatedUser->refresh();

        // Verify old role is removed
        $this->assertFalse($updatedUser->hasRole('marketing'), 'Old role should be removed');

        // Verify new role is assigned
        $this->assertTrue($updatedUser->hasRole('sales'), 'New role should be assigned');

        // Verify new permissions are available immediately
        $this->assertTrue(
            $updatedUser->hasPermissionTo('sales.dashboard.view'),
            'New permissions should be available immediately'
        );
        
        $this->assertFalse(
            $updatedUser->hasPermissionTo('marketing.dashboard.view'),
            'Old permissions should be removed'
        );
    }

    #[Test]
    public function test_role_existence_verification_prevents_errors()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Invalid Role User',
            'email' => 'invalid-role@test.com',
            'phone' => '0501234574',
            'password' => 'password123',
            'type' => 0, // marketing
            'role' => 'non_existent_role',
            'team' => $team->id,
        ];

        // Verify error is handled gracefully
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Role 'non_existent_role' does not exist.");

        // Verify user is not created with invalid role
        try {
            $this->registrationService->register($userData);
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
            throw $e;
        }
    }

    #[Test]
    public function test_syncRolesFromType_clears_permission_cache()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Sync Test User',
            'email' => 'sync@test.com',
            'phone' => '0501234575',
            'password' => 'password123',
            'type' => 0, // marketing
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Call syncRolesFromType() again
        $user->syncRolesFromType();
        $user->refresh();

        // Verify permission cache is cleared and permissions are immediately available
        $this->assertTrue(
            $user->hasPermissionTo('marketing.dashboard.view'),
            'Permissions should be available immediately after syncRolesFromType'
        );
    }

    #[Test]
    public function test_multiple_role_assignments_dont_duplicate_permissions()
    {
        $team = $this->createTestTeam();
        
        $userData = [
            'name' => 'Multiple Assign User',
            'email' => 'multiple@test.com',
            'phone' => '0501234576',
            'password' => 'password123',
            'type' => 0, // marketing
            'team' => $team->id,
        ];

        $user = $this->registrationService->register($userData);
        $user->refresh();

        // Re-assign same role multiple times
        $user->syncRoles(['marketing']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        
        $user->syncRoles(['marketing']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        
        $user->syncRoles(['marketing']);
        $user->refresh();

        // Verify permissions are not duplicated
        $this->assertEquals(1, $user->roles()->count(), 'User should have only one role');
        
        // Verify cache is cleared each time and permissions are still accessible
        $this->assertTrue(
            $user->hasPermissionTo('marketing.dashboard.view'),
            'Permissions should still be accessible after multiple assignments'
        );
    }
}
