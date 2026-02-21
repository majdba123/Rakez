<?php

namespace Tests\Feature\Credit;

use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;
use App\Models\User;

/**
 * Feature tests for CreditNotificationController (credit notifications tab).
 */
class CreditNotificationControllerTest extends BasePermissionTestCase
{
    private User $creditUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creditUser = $this->createUserWithType('credit');
    }

    #[Test]
    public function credit_user_can_list_notifications(): void
    {
        $response = $this->actingAs($this->creditUser, 'sanctum')
            ->getJson('/api/credit/notifications');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'pagination' => [
                    'total',
                    'count',
                    'per_page',
                    'current_page',
                    'total_pages',
                    'has_more_pages',
                ],
            ],
        ]);
    }

    #[Test]
    public function credit_user_can_mark_notification_as_read(): void
    {
        $notification = \App\Models\UserNotification::create([
            'user_id' => $this->creditUser->id,
            'message' => 'Test notification',
            'status' => 'pending',
            'event_type' => 'test',
        ]);

        $response = $this->actingAs($this->creditUser, 'sanctum')
            ->postJson("/api/credit/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    #[Test]
    public function unauthenticated_user_cannot_list_notifications(): void
    {
        $response = $this->getJson('/api/credit/notifications');
        $this->assertNotEquals(200, $response->status(), 'Unauthenticated request must not succeed');
    }
}
