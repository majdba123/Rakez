<?php

namespace Tests\Feature\Contract;

use App\Enums\ContractWorkflowStatus;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectArchiveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $pmManager;
    protected User $pmStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->pmManager = User::factory()->create([
            'type' => 'project_management',
            'is_manager' => true,
        ]);
        $this->pmManager->syncRolesFromType();

        $this->pmStaff = User::factory()->create([
            'type' => 'project_management',
            'is_manager' => false,
        ]);
        $this->pmStaff->syncRolesFromType();
    }

    public function test_completed_contract_can_request_archive_and_becomes_pending_archive(): void
    {
        $contract = Contract::factory()->create([
            'status' => ContractWorkflowStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->pmManager, 'sanctum')
            ->postJson("/api/contracts/{$contract->id}/archive-request", [
                'archive_note' => 'Need signboard removal confirmation.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'يرجى التأكد من إزالة لوحات المشروع قبل تأكيد الأرشفة.')
            ->assertJsonPath('data.status', ContractWorkflowStatus::PendingArchive->value)
            ->assertJsonPath('data.can_confirm_archive', true);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractWorkflowStatus::PendingArchive->value,
            'archive_requested_by' => $this->pmManager->id,
            'archive_note' => 'Need signboard removal confirmation.',
        ]);
    }

    public function test_pending_archive_project_appears_in_archived_listing(): void
    {
        $pendingArchive = Contract::factory()->create([
            'status' => ContractWorkflowStatus::PendingArchive->value,
            'archive_requested_at' => now(),
            'archive_requested_by' => $this->pmManager->id,
        ]);

        Contract::factory()->create([
            'status' => ContractWorkflowStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->pmManager, 'sanctum')
            ->getJson('/api/contracts/archived');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $pendingArchive->id);
    }

    public function test_pending_archive_project_can_be_confirmed_and_becomes_archived(): void
    {
        $contract = Contract::factory()->create([
            'status' => ContractWorkflowStatus::PendingArchive->value,
            'archive_requested_at' => now()->subDay(),
            'archive_requested_by' => $this->pmManager->id,
        ]);

        $response = $this->actingAs($this->pmManager, 'sanctum')
            ->postJson("/api/contracts/{$contract->id}/archive-confirm", [
                'boards_removed_confirmed' => true,
                'archive_note' => 'Boards removed on site.',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'تم تأكيد إزالة لوحات المشروع وأرشفة المشروع بنجاح.')
            ->assertJsonPath('data.status', ContractWorkflowStatus::Archived->value)
            ->assertJsonPath('data.can_confirm_archive', false);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractWorkflowStatus::Archived->value,
            'archive_confirmed_by' => $this->pmManager->id,
            'archived_by' => $this->pmManager->id,
            'boards_removed_confirmed_by' => $this->pmManager->id,
            'archive_note' => 'Boards removed on site.',
        ]);
    }

    public function test_cannot_confirm_archive_unless_status_is_pending_archive(): void
    {
        $contract = Contract::factory()->create([
            'status' => ContractWorkflowStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->pmManager, 'sanctum')
            ->postJson("/api/contracts/{$contract->id}/archive-confirm", [
                'boards_removed_confirmed' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_already_archived_project_cannot_be_archived_again(): void
    {
        $contract = Contract::factory()->create([
            'status' => ContractWorkflowStatus::Archived->value,
            'archived_at' => now(),
            'archived_by' => $this->pmManager->id,
        ]);

        $response = $this->actingAs($this->pmManager, 'sanctum')
            ->postJson("/api/contracts/{$contract->id}/archive-request");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_unauthorized_user_cannot_archive_or_confirm(): void
    {
        $contract = Contract::factory()->create([
            'status' => ContractWorkflowStatus::Completed->value,
        ]);

        $archiveResponse = $this->actingAs($this->pmStaff, 'sanctum')
            ->postJson("/api/contracts/{$contract->id}/archive-request");

        $archiveResponse->assertForbidden();

        $confirmResponse = $this->actingAs($this->pmStaff, 'sanctum')
            ->postJson("/api/contracts/{$contract->id}/archive-confirm", [
                'boards_removed_confirmed' => true,
            ]);

        $confirmResponse->assertForbidden();
    }
}
