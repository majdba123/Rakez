<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;

/**
 * Login endpoint security & contract tests.
 *
 * Covers:
 *  - Active user login success + payload shape
 *  - Deactivated user blocked at login
 *  - Soft-deleted user blocked at login
 *  - Wrong credentials rejected
 *  - No sensitive fields leak in response
 *  - Roles, permissions, access profile returned
 */
class LoginSecurityTest extends BasePermissionTestCase
{
    // ─── Helpers ──────────────────────────────────────────────

    private function loginPayload(string $email = 'test@rakez.test', string $password = 'password'): array
    {
        return ['email' => $email, 'password' => $password];
    }

    /**
     * Fields that must NEVER appear in the login response user object.
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

    // ─── Success ──────────────────────────────────────────────

    #[Test]
    public function active_user_can_login_and_receives_full_payload(): void
    {
        $user = $this->createSalesStaff([
            'email'    => 'sales@rakez.test',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', $this->loginPayload('sales@rakez.test'))
            ->assertOk()
            ->assertJsonStructure([
                'access_token',
                'user' => ['id', 'name', 'email', 'phone', 'type', 'is_manager', 'team_id', 'job_title', 'department', 'is_active'],
                'roles',
                'roles_display',
                'permissions',
                'access',
            ]);

        $this->assertEquals($user->id, $response->json('user.id'));
        $this->assertNotEmpty($response->json('access_token'));
    }

    #[Test]
    public function login_returns_roles_and_permissions_for_frontend(): void
    {
        $this->createSalesStaff([
            'email'    => 'sales@rakez.test',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', $this->loginPayload('sales@rakez.test'))
            ->assertOk();

        $roles = $response->json('roles');
        $this->assertIsArray($roles);
        $this->assertNotEmpty($roles, 'Roles should not be empty for a typed user');

        $rolesDisplay = $response->json('roles_display');
        $this->assertIsArray($rolesDisplay);
        $this->assertNotEmpty($rolesDisplay, 'Displayed roles should not be empty for a typed user');

        $permissions = $response->json('permissions');
        $this->assertIsArray($permissions);

        $access = $response->json('access');
        $this->assertIsArray($access);
    }

    // ─── Security: no sensitive data leaks ────────────────────

    #[Test]
    public function login_response_does_not_leak_sensitive_fields(): void
    {
        $this->createSalesStaff([
            'email'    => 'sales@rakez.test',
            'password' => bcrypt('password'),
            'salary'   => 15000,
            'iban'     => 'SA1234567890',
            'identity_number' => '1234567890',
        ]);

        $response = $this->postJson('/api/login', $this->loginPayload('sales@rakez.test'))
            ->assertOk();

        $userPayload = $response->json('user');

        foreach ($this->sensitiveFields() as $field) {
            $this->assertArrayNotHasKey($field, $userPayload, "Sensitive field '{$field}' leaked in login response");
        }
    }

    // ─── Blocked: deactivated user ────────────────────────────

    #[Test]
    public function deactivated_user_cannot_login(): void
    {
        $this->createSalesStaff([
            'email'     => 'inactive@rakez.test',
            'password'  => bcrypt('password'),
            'is_active' => false,
        ]);

        $this->postJson('/api/login', $this->loginPayload('inactive@rakez.test'))
            ->assertStatus(403);
    }

    // ─── Blocked: soft-deleted user ───────────────────────────

    #[Test]
    public function soft_deleted_user_cannot_login(): void
    {
        $user = $this->createSalesStaff([
            'email'    => 'deleted@rakez.test',
            'password' => bcrypt('password'),
        ]);

        $user->delete(); // sets deleted_at

        $this->postJson('/api/login', $this->loginPayload('deleted@rakez.test'))
            ->assertUnauthorized();
    }

    // ─── Blocked: wrong credentials ──────────────────────────

    #[Test]
    public function wrong_password_returns_401(): void
    {
        $this->createSalesStaff([
            'email'    => 'sales@rakez.test',
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/login', $this->loginPayload('sales@rakez.test', 'wrong-password'))
            ->assertUnauthorized();
    }

    #[Test]
    public function nonexistent_email_returns_401(): void
    {
        $this->postJson('/api/login', $this->loginPayload('nobody@rakez.test'))
            ->assertUnauthorized();
    }

    // ─── Validation ──────────────────────────────────────────

    #[Test]
    public function login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422);
    }

    #[Test]
    public function top_level_admin_role_is_presented_as_admin_in_login_roles_display(): void
    {
        $this->createSuperAdmin([
            'email' => 'panel-admin@rakez.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', $this->loginPayload('panel-admin@rakez.test'))
            ->assertOk();

        $this->assertContains('super_admin', $response->json('roles') ?? []);
        $this->assertContains('admin', $response->json('roles_display') ?? []);
    }
}
