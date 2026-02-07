<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test User model syncRolesFromType permission cache clearing
 */
class UserPermissionCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Create permissions
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

    #[Test]
    public function test_syncRolesFromType_clears_permission_cache()
    {
        // Create user instance
        $user = User::factory()->create([
            'type' => 'marketing',
            'is_manager' => false,
        ]);

        // Verify user doesn't have role yet
        $this->assertFalse($user->hasRole('marketing'));

        // Call syncRolesFromType()
        $user->syncRolesFromType();
        $user->refresh();

        // Verify role is assigned
        $this->assertTrue($user->hasRole('marketing'));

        // Verify permission cache is cleared and permissions are immediately available
        $this->assertTrue(
            $user->hasPermissionTo('marketing.dashboard.view'),
            'Permissions should be available immediately after syncRolesFromType'
        );
    }

    #[Test]
    public function test_syncRolesFromType_verifies_role_exists()
    {
        // Create user with type that has no corresponding role
        $user = User::factory()->create([
            'type' => 'invalid_type',
            'is_manager' => false,
        ]);

        // Call syncRolesFromType() - should not throw error
        $user->syncRolesFromType();
        $user->refresh();

        // Verify no error is thrown
        $this->assertTrue(true, 'No exception should be thrown');

        // Verify role is not assigned if it doesn't exist
        $this->assertFalse($user->hasRole('invalid_type'));
        $this->assertEquals(0, $user->roles()->count(), 'User should have no roles if role does not exist');
    }

    #[Test]
    public function test_syncRolesFromType_handles_sales_leader_correctly()
    {
        // Create sales user with is_manager = true
        $user = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
        ]);

        // Call syncRolesFromType()
        $user->syncRolesFromType();
        $user->refresh();

        // Verify sales_leader role is assigned (not sales)
        $this->assertTrue($user->hasRole('sales_leader'), 'Sales manager should have sales_leader role');
        $this->assertFalse($user->hasRole('sales'), 'Sales manager should not have sales role');

        // Verify permission cache is cleared and permissions are available
        $this->assertTrue(
            $user->hasPermissionTo('sales.dashboard.view'),
            'Sales leader should have sales permissions immediately'
        );
        
        $this->assertTrue(
            $user->hasPermissionTo('sales.waiting_list.convert'),
            'Sales leader should have leader-specific permissions immediately'
        );
    }

    #[Test]
    public function test_syncRolesFromType_clears_cache_each_time()
    {
        $user = User::factory()->create([
            'type' => 'marketing',
            'is_manager' => false,
        ]);

        // First call
        $user->syncRolesFromType();
        $user->refresh();
        $this->assertTrue($user->hasPermissionTo('marketing.dashboard.view'));

        // Second call - should clear cache again
        $user->syncRolesFromType();
        $user->refresh();
        $this->assertTrue($user->hasPermissionTo('marketing.dashboard.view'));

        // Third call - should clear cache again
        $user->syncRolesFromType();
        $user->refresh();
        $this->assertTrue($user->hasPermissionTo('marketing.dashboard.view'));

        // Verify user still has only one role
        $this->assertEquals(1, $user->roles()->count());
    }

    #[Test]
    public function test_syncRolesFromType_works_for_all_user_types()
    {
        $types = ['admin', 'sales', 'marketing', 'project_management', 'hr', 'editor'];
        
        foreach ($types as $type) {
            $user = User::factory()->create([
                'type' => $type,
                'is_manager' => false,
            ]);

            $user->syncRolesFromType();
            $user->refresh();

            $this->assertTrue(
                $user->hasRole($type),
                "User with type '{$type}' should have role '{$type}'"
            );

            // Verify permissions are immediately available
            $permissions = $user->getAllPermissions();
            $this->assertGreaterThan(0, $permissions->count(), "User with type '{$type}' should have permissions");
        }
    }
}
