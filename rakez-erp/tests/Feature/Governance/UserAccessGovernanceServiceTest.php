<?php

namespace Tests\Feature\Governance;

use App\Services\Governance\UserGovernanceService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class UserAccessGovernanceServiceTest extends BasePermissionTestCase
{
    #[Test]
    public function super_admin_can_assign_and_revoke_additional_business_roles_on_users(): void
    {
        $actor = $this->createSuperAdmin([
            'email' => 'panel-super-admin@example.com',
        ]);

        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);

        $user = $service->create([
            'name' => 'Access Managed User',
            'email' => 'access-managed-user@example.com',
            'phone' => '0500000001',
            'password' => 'secret-password',
            'type' => 'sales',
            'is_manager' => false,
            'is_active' => true,
            'additional_roles' => ['marketing', 'credit'],
            'governance_roles' => [],
            'direct_permissions' => ['contracts.view'],
        ]);

        $this->assertTrue($user->hasRole('sales'));
        $this->assertTrue($user->hasRole('marketing'));
        $this->assertTrue($user->hasRole('credit'));
        $this->assertFalse($user->hasRole('admin'));

        $updated = $service->update($user, [
            'name' => 'Access Managed User',
            'email' => 'access-managed-user@example.com',
            'phone' => '0500000001',
            'type' => 'sales',
            'is_manager' => false,
            'is_active' => false,
            'additional_roles' => ['marketing'],
            'governance_roles' => ['super_admin'],
            'direct_permissions' => ['notifications.view'],
        ]);

        $this->assertTrue($updated->hasRole('sales'));
        $this->assertTrue($updated->hasRole('marketing'));
        $this->assertFalse($updated->hasRole('credit'));
        $this->assertTrue($updated->hasRole('super_admin'));
        $this->assertEqualsCanonicalizing(['notifications.view'], $updated->permissions()->pluck('name')->all());
    }

    #[Test]
    public function filament_user_service_rejects_new_legacy_admin_type_assignments(): void
    {
        $actor = $this->createSuperAdmin([
            'email' => 'governance-super-admin@example.com',
        ]);

        $this->actingAs($actor);

        $service = app(UserGovernanceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('legacy admin user type');

        $service->create([
            'name' => 'Legacy Admin Attempt',
            'email' => 'legacy-admin-attempt@example.com',
            'phone' => '0500000002',
            'password' => 'secret-password',
            'type' => 'admin',
            'is_manager' => false,
            'is_active' => true,
            'additional_roles' => [],
            'governance_roles' => [],
            'direct_permissions' => [],
        ]);
    }
}
