<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\AssistantKnowledgeEntries\AssistantKnowledgeEntryResource;
use App\Models\AssistantKnowledgeEntry;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\ExclusiveProjectRequest;
use App\Models\ProjectMedia;
use App\Models\SalesReservation;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Governance\GovernanceCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class Phase5OversightRolloutTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Contracts & Projects',
            'Sales Oversight',
            'Inventory Oversight',
            'AI & Knowledge',
            'Requests & Workflow',
        ]);
    }

    #[Test]
    public function approvals_center_v1_is_available_to_workflow_and_erp_admins(): void
    {
        $workflowAdmin = $this->createGovernanceUser('workflow_admin');
        $erpAdmin = $this->createGovernanceUser('erp_admin');
        $auditor = $this->createGovernanceUser('auditor_readonly');
        $team = Team::create([
            'code' => 'P5-WF-' . random_int(1000, 9999),
            'name' => 'Phase 5 Workflow Team',
            'description' => 'Workflow team for approvals center tests',
        ]);

        ExclusiveProjectRequest::factory()->create(['status' => 'pending']);
        Task::create([
            'task_name' => 'Phase 5 Approval Task',
            'section' => 'workflow',
            'team_id' => $team->id,
            'due_at' => now()->addDay(),
            'assigned_to' => $workflowAdmin->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'created_by' => $erpAdmin->id,
        ]);
        UserNotification::create([
            'user_id' => null,
            'message' => 'Pending workflow notification.',
            'status' => 'pending',
            'event_type' => 'workflow_pending',
        ]);

        $this->actingAs($workflowAdmin)
            ->get('/admin/approvals-center')
            ->assertOk()
            ->assertSeeText('Approvals Center')
            ->assertDontSeeText('Pending Exclusive Requests')
            ->assertDontSeeText('exclusive request');

        $this->actingAs($erpAdmin)->get('/admin/approvals-center')->assertOk();
        $this->actingAs($auditor)->get('/admin/approvals-center')->assertForbidden();
    }

    #[Test]
    public function workflow_admin_cannot_access_contracts_projects_request_review_pages(): void
    {
        $workflowAdmin = $this->createGovernanceUser('workflow_admin');

        $request = ExclusiveProjectRequest::factory()->create([
            'status' => 'pending',
        ]);

        $this->actingAs($workflowAdmin)->get('/admin/exclusive-project-requests')->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    #[Test]
    public function project_media_is_exposed_as_read_only_image_review(): void
    {
        $projectsAdmin = $this->createGovernanceUser('projects_admin');
        $salesAdmin = $this->createGovernanceUser('sales_admin');

        $contract = Contract::factory()->create([
            'project_name' => 'Phase 5 Media Project',
        ]);

        $media = ProjectMedia::create([
            'contract_id' => $contract->id,
            'type' => 'hero_image',
            'url' => 'https://example.com/project-media.jpg',
            'department' => 'photography',
        ]);

        $this->actingAs($projectsAdmin)->get('/admin/project-media')->assertOk();
        $this->actingAs($projectsAdmin)->get("/admin/project-media/{$media->id}")->assertOk();
        $this->actingAs($salesAdmin)->get('/admin/project-media')->assertForbidden();
    }

    #[Test]
    public function reservations_oversight_now_has_read_only_detail_view(): void
    {
        $salesAdmin = $this->createGovernanceUser('sales_admin');
        $erpAdmin = $this->createGovernanceUser('erp_admin');
        $marketingEmployee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $marketingEmployee->syncRolesFromType();

        $contract = Contract::factory()->create([
            'project_name' => 'Phase 5 Reservation Project',
        ]);

        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'unit_number' => 'P5-201',
            'status' => 'available',
        ]);

        $reservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $marketingEmployee->id,
            'status' => 'confirmed',
            'client_name' => 'Phase 5 Reservation Client',
            'credit_status' => 'pending',
            'purchase_mechanism' => 'cash',
        ]);

        $this->actingAs($salesAdmin)->get('/admin/sales-reservations')->assertOk();
        $this->actingAs($salesAdmin)->get("/admin/sales-reservations/{$reservation->id}")->assertOk();
        $this->actingAs($erpAdmin)->get("/admin/sales-reservations/{$reservation->id}")->assertOk();
    }

    #[Test]
    public function ai_knowledge_is_now_a_governed_admin_crud_surface(): void
    {
        $aiAdmin = $this->createGovernanceUser('ai_admin');
        $erpAdmin = $this->createGovernanceUser('erp_admin');
        $workflowAdmin = $this->createGovernanceUser('workflow_admin');

        $entry = AssistantKnowledgeEntry::create([
            'module' => 'assistant',
            'page_key' => 'phase5',
            'title' => 'Phase 5 Knowledge',
            'content_md' => 'Read-only governance knowledge entry.',
            'tags' => ['phase5', 'governance'],
            'roles' => ['ai_admin'],
            'permissions' => ['manage-ai-knowledge'],
            'language' => 'ar',
            'is_active' => true,
            'priority' => 10,
            'updated_by' => $aiAdmin->id,
        ]);

        $this->actingAs($aiAdmin)->get('/admin/assistant-knowledge-entries')->assertOk();
        $this->actingAs($aiAdmin)->get('/admin/assistant-knowledge-entries/create')->assertOk();
        $this->actingAs($aiAdmin)->get("/admin/assistant-knowledge-entries/{$entry->id}")->assertOk();
        $this->actingAs($aiAdmin)->get("/admin/assistant-knowledge-entries/{$entry->id}/edit")->assertOk();
        $this->actingAs($erpAdmin)->get('/admin/assistant-knowledge-entries')->assertOk();
        $this->actingAs($workflowAdmin)->get('/admin/assistant-knowledge-entries')->assertForbidden();

        $this->actingAs($aiAdmin);

        $this->assertTrue(AssistantKnowledgeEntryResource::canCreate());
        $this->assertTrue(AssistantKnowledgeEntryResource::canEdit($entry));
        $this->assertTrue(AssistantKnowledgeEntryResource::canDelete($entry));
        $this->assertTrue(AssistantKnowledgeEntryResource::canDeleteAny());
        $this->assertFalse(AssistantKnowledgeEntryResource::canForceDelete($entry));
        $this->assertFalse(AssistantKnowledgeEntryResource::canRestore($entry));
    }

    #[Test]
    public function inventory_editor_and_ai_log_surfaces_match_backend_capabilities(): void
    {
        $inventoryAdmin = $this->createGovernanceUser('inventory_admin');
        $aiAdmin = $this->createGovernanceUser('ai_admin');

        $contract = Contract::factory()->create([
            'project_name' => 'Phase 5 Inventory Project',
        ]);

        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'unit_number' => 'P5-INV-1',
        ]);

        $this->actingAs($inventoryAdmin)->get('/admin/inventory-overview')->assertOk();
        $this->actingAs($inventoryAdmin)->get('/admin/inventory-units')->assertOk();
        $this->actingAs($inventoryAdmin)->get("/admin/inventory-units/{$unit->id}/edit")->assertOk();

        $this->actingAs($aiAdmin)->get('/admin/ai-overview')->assertOk();
        $this->actingAs($aiAdmin)->get('/admin/ai-interaction-logs')->assertOk();
        $this->actingAs($aiAdmin)->get('/admin/ai-audit-entries')->assertOk();
    }

    #[Test]
    public function postponed_temporary_grants_stay_out_of_catalog_when_rollout_disabled(): void
    {
        config(['governance.temporary_permissions.enabled' => false]);

        $erpAdmin = $this->createGovernanceUser('erp_admin');

        $catalog = app(GovernanceCatalog::class);

        $this->assertArrayNotHasKey('admin.temp_permissions.view', $catalog->permissionOptions());
        $this->assertArrayNotHasKey('admin.temp_permissions.manage', $catalog->permissionOptions());

        $this->actingAs($erpAdmin)
            ->get('/admin/governance-temporary-permissions')
            ->assertForbidden();

        $this->actingAs($erpAdmin)
            ->get('/admin/permissions')
            ->assertOk()
            ->assertDontSeeText('admin.temp_permissions.view')
            ->assertDontSeeText('admin.temp_permissions.manage');
    }

    protected function createGovernanceUser(string $role): User
    {
        $user = $this->createDefaultUser([
            'is_active' => true,
            'email' => "{$role}-" . uniqid() . '@example.com',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
