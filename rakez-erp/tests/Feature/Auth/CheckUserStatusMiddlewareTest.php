<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the check_status middleware applied to auth:sanctum routes.
 *
 * Verifies that deactivated users with valid Sanctum tokens are blocked
 * from accessing protected API endpoints (403).
 */
class CheckUserStatusMiddlewareTest extends BasePermissionTestCase
{
    #[Test]
    public function active_user_can_access_api_user_endpoint(): void
    {
        $user = $this->createSalesStaff();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'type']);
    }

    #[Test]
    public function deactivated_user_is_blocked_from_api_user_endpoint(): void
    {
        $user = $this->createSalesStaff(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertStatus(403);
    }

    #[Test]
    public function deactivated_user_is_blocked_from_access_profile_endpoint(): void
    {
        $user = $this->createSalesStaff(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/access/profile')
            ->assertStatus(403);
    }

    #[Test]
    public function deactivated_user_is_blocked_from_authenticated_routes_outside_the_original_top_group(): void
    {
        $user = $this->createSalesStaff(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/teams/index')
            ->assertStatus(403);
    }

    #[Test]
    public function deactivated_admin_is_blocked_from_api(): void
    {
        $user = $this->createAdmin(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertStatus(403);
    }

    #[Test]
    public function reactivated_user_regains_access(): void
    {
        $user = $this->createSalesStaff(['is_active' => false]);

        // Blocked while inactive
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertStatus(403);

        // Reactivate
        $user->update(['is_active' => true]);

        // Now allowed
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();
    }

    #[Test]
    public function soft_deleted_user_with_valid_session_is_blocked_from_api_user_endpoint(): void
    {
        $user = $this->createSalesStaff();
        $user->delete();

        $this->actingAs(User::withTrashed()->findOrFail($user->id), 'sanctum')
            ->getJson('/api/user')
            ->assertStatus(403);
    }
}
