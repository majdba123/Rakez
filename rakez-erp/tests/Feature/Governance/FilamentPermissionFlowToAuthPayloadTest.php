<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\DirectPermissions\Pages\EditDirectPermissions;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class FilamentPermissionFlowToAuthPayloadTest extends BasePermissionTestCase
{
    #[Test]
    public function direct_permission_changes_in_filament_flow_to_login_and_current_user_payloads(): void
    {
        $permission = 'contracts.view';

        $actor = $this->createSuperAdmin([
            'email' => 'filament-super-admin@example.com',
            'is_active' => true,
        ]);

        $target = $this->createSalesStaff([
            'email' => 'filament-flow-user@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->syncDirectPermissionsInFilament($actor, $target, [$permission]);

        $this->assertTrue(
            $target->fresh()->permissions()->pluck('name')->contains($permission),
            'Direct permission should be stored in DB after Filament save.'
        );

        $loginAfterGrant = $this->postJson('/api/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertContains($permission, $loginAfterGrant->json('permissions') ?? []);

        $userAfterGrant = $this->actingAs($target->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $this->assertContains($permission, $userAfterGrant->json('permissions') ?? []);

        $this->syncDirectPermissionsInFilament($actor, $target, []);

        $this->assertFalse(
            $target->fresh()->permissions()->pluck('name')->contains($permission),
            'Direct permission should be removed from DB after Filament revoke.'
        );

        $loginAfterRevoke = $this->postJson('/api/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertNotContains($permission, $loginAfterRevoke->json('permissions') ?? []);

        $userAfterRevoke = $this->actingAs($target->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $this->assertNotContains($permission, $userAfterRevoke->json('permissions') ?? []);
    }

    private function syncDirectPermissionsInFilament(User $actor, User $target, array $permissions): void
    {
        $this->actingAs($actor);

        Livewire::test(EditDirectPermissions::class, ['record' => $target->getRouteKey()])
            ->set('data.direct_permissions', $permissions)
            ->call('save')
            ->assertHasNoErrors();

        auth('web')->logout();
        $this->flushSession();
    }
}

