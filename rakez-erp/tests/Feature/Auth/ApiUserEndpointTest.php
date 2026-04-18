<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for GET /api/user — the authenticated-user profile endpoint.
 *
 * Verifies:
 *  - Returns only safe fields (no salary, iban, identity_number, etc.)
 *  - Includes roles and permissions
 *  - Requires authentication
 */
class ApiUserEndpointTest extends BasePermissionTestCase
{
    /**
     * Fields that must NEVER appear in the /api/user response.
     */
    private function sensitiveFields(): array
    {
        return [
            'salary',
            'iban',
            'identity_number',
            'cv_path',
            'password',
            'remember_token',
        ];
    }

    /**
     * Fields that MUST appear in the /api/user response.
     */
    private function expectedFields(): array
    {
        return [
            'id', 'name', 'email', 'phone', 'type',
            'is_manager', 'team_id', 'job_title', 'department', 'is_active',
            'user', 'roles', 'roles_display', 'permissions', 'access',
        ];
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    #[Test]
    public function returns_expected_fields_only(): void
    {
        $user = $this->createSalesStaff([
            'salary'          => 15000,
            'iban'            => 'SA9999999999',
            'identity_number' => '1234567890',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $data = $response->json();

        // All expected fields present
        foreach ($this->expectedFields() as $field) {
            $this->assertArrayHasKey($field, $data, "Expected field '{$field}' missing from /api/user");
        }

        $this->assertSame($data['id'], $data['user']['id']);
        $this->assertSame($data['email'], $data['user']['email']);

        // No sensitive fields
        foreach ($this->sensitiveFields() as $field) {
            $this->assertArrayNotHasKey($field, $data, "Sensitive field '{$field}' leaked in /api/user");
            $this->assertArrayNotHasKey($field, $data['user'], "Sensitive field '{$field}' leaked in nested /api/user payload");
        }
    }

    #[Test]
    public function returns_roles_as_array(): void
    {
        $user = $this->createSalesStaff();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $roles = $response->json('roles');
        $this->assertIsArray($roles);
        $this->assertNotEmpty($roles);
    }

    #[Test]
    public function returns_permissions_as_array(): void
    {
        $user = $this->createSalesStaff();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $permissions = $response->json('permissions');
        $this->assertIsArray($permissions);
    }

    #[Test]
    public function returns_access_profile_and_nested_user_contract(): void
    {
        $user = $this->createSalesStaff();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $response
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone', 'type', 'is_manager', 'team_id', 'job_title', 'department', 'is_active'],
                'roles',
                'roles_display',
                'permissions',
                'access' => [
                    'user' => ['id', 'name', 'email', 'type'],
                    'frontend' => ['sections'],
                ],
            ]);
    }

    #[Test]
    public function admin_user_does_not_leak_extra_fields(): void
    {
        $user = $this->createAdmin([
            'salary'          => 50000,
            'iban'            => 'SA0000000000',
            'identity_number' => '9999999999',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $data = $response->json();

        foreach ($this->sensitiveFields() as $field) {
            $this->assertArrayNotHasKey($field, $data, "Sensitive field '{$field}' leaked for admin in /api/user");
        }
    }

    #[Test]
    public function top_level_admin_role_is_presented_as_admin_in_roles_display(): void
    {
        $user = $this->createSuperAdmin([
            'email' => 'top-admin@rakez.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $this->assertContains('super_admin', $response->json('roles') ?? []);
        $this->assertContains('admin', $response->json('roles_display') ?? []);
    }
}
