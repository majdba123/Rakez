<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserNotificationMarkReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_mark_read_numeric_id_updates_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $n = UserNotification::create([
            'user_id' => $user->id,
            'message' => 'test',
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/user/notifications/{$n->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $n->id)
            ->assertJsonPath('data.status', 'read')
            ->assertJsonPath('data.client_only', false);

        $this->assertSame('read', $n->fresh()->status);
    }

    public function test_patch_mark_read_local_id_returns_200_without_db_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $localId = 'local-1776188056406-6dw6b97v5f6';

        $response = $this->patchJson("/api/user/notifications/{$localId}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $localId)
            ->assertJsonPath('data.client_only', true);

        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_patch_mark_read_wrong_user_gets_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $n = UserNotification::create([
            'user_id' => $owner->id,
            'message' => 'private',
            'status' => 'pending',
        ]);

        $this->patchJson("/api/user/notifications/{$n->id}/read")
            ->assertStatus(404);
    }

    public function test_shorthand_notifications_path_also_matches(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $localId = 'local-abc123';

        $this->patchJson("/api/notifications/{$localId}/read")
            ->assertStatus(200)
            ->assertJsonPath('data.client_only', true);
    }

    public function test_non_numeric_non_local_id_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/user/notifications/not-a-uuid-or-local/read')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
