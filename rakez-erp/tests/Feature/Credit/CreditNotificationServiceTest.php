<?php

namespace Tests\Feature\Credit;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Credit\CreditNotificationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class CreditNotificationServiceTest extends BasePermissionTestCase
{
    #[Test]
    public function mark_department_notification_as_read_marks_only_credit_department_notifications(): void
    {
        $creditUser = User::factory()->create([
            'type' => 'credit',
            'is_active' => true,
        ]);
        $creditUser->assignRole('credit');

        $salesUser = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $salesUser->assignRole('sales');

        $creditNotification = UserNotification::query()->create([
            'user_id' => $creditUser->id,
            'message' => 'Credit pending',
            'status' => 'pending',
            'event_type' => 'credit.pending',
        ]);

        $salesNotification = UserNotification::query()->create([
            'user_id' => $salesUser->id,
            'message' => 'Sales pending',
            'status' => 'pending',
            'event_type' => 'sales.pending',
        ]);

        $updated = app(CreditNotificationService::class)
            ->markDepartmentNotificationAsRead('credit', $creditNotification->id);

        $this->assertSame('read', $updated->status);
        $this->assertDatabaseHas('user_notifications', [
            'id' => $creditNotification->id,
            'status' => 'read',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'id' => $salesNotification->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function mark_all_department_notifications_as_read_updates_only_pending_notifications_for_that_department(): void
    {
        $creditUser = User::factory()->create([
            'type' => 'credit',
            'is_active' => true,
        ]);
        $creditUser->assignRole('credit');

        $otherUser = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $otherUser->assignRole('sales');

        $creditPendingOne = UserNotification::query()->create([
            'user_id' => $creditUser->id,
            'message' => 'Credit pending one',
            'status' => 'pending',
            'event_type' => 'credit.pending',
        ]);
        $creditPendingTwo = UserNotification::query()->create([
            'user_id' => $creditUser->id,
            'message' => 'Credit pending two',
            'status' => 'pending',
            'event_type' => 'credit.pending',
        ]);
        $creditAlreadyRead = UserNotification::query()->create([
            'user_id' => $creditUser->id,
            'message' => 'Credit read',
            'status' => 'read',
            'event_type' => 'credit.read',
        ]);
        $otherPending = UserNotification::query()->create([
            'user_id' => $otherUser->id,
            'message' => 'Sales pending',
            'status' => 'pending',
            'event_type' => 'sales.pending',
        ]);

        $updatedCount = app(CreditNotificationService::class)
            ->markAllDepartmentNotificationsAsRead('credit');

        $this->assertSame(2, $updatedCount);

        $this->assertDatabaseHas('user_notifications', [
            'id' => $creditPendingOne->id,
            'status' => 'read',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'id' => $creditPendingTwo->id,
            'status' => 'read',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'id' => $creditAlreadyRead->id,
            'status' => 'read',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'id' => $otherPending->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function credit_notifications_read_all_endpoint_marks_authenticated_users_pending_notifications_as_read(): void
    {
        $creditUser = User::factory()->create([
            'type' => 'credit',
            'is_active' => true,
        ]);
        $creditUser->assignRole('credit');

        UserNotification::query()->create([
            'user_id' => $creditUser->id,
            'message' => 'Pending one',
            'status' => 'pending',
            'event_type' => 'credit.pending',
        ]);
        UserNotification::query()->create([
            'user_id' => $creditUser->id,
            'message' => 'Pending two',
            'status' => 'pending',
            'event_type' => 'credit.pending',
        ]);

        $response = $this->actingAs($creditUser, 'sanctum')
            ->postJson('/api/credit/notifications/read-all');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $creditUser->id,
            'status' => 'pending',
        ]);
    }
}
