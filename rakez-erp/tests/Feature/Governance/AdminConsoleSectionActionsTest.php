<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\CreateAssistantKnowledgeEntry;
use App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\EditAssistantKnowledgeEntry;
use App\Filament\Admin\Resources\AdminNotifications\Pages\ListAdminNotifications;
use App\Filament\Admin\Resources\Contracts\Pages\ListContracts;
use App\Filament\Admin\Resources\EmployeeContracts\Pages\ListEmployeeContracts;
use App\Filament\Admin\Resources\EmployeeWarnings\Pages\ListEmployeeWarnings;
use App\Filament\Admin\Resources\HrTeams\Pages\CreateHrTeam;
use App\Filament\Admin\Resources\HrTeams\Pages\EditHrTeam;
use App\Filament\Admin\Resources\InventoryUnits\Pages\EditInventoryUnit;
use App\Filament\Admin\Resources\MarketingTasks\Pages\CreateMarketingTask;
use App\Filament\Admin\Resources\MarketingTasks\Pages\EditMarketingTask;
use App\Filament\Admin\Resources\MarketingTasks\Pages\ListMarketingTasks;
use App\Filament\Admin\Resources\SalesReservations\Pages\ListSalesReservations;
use App\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications;
use App\Filament\Admin\Resources\WorkflowTasks\Pages\ListWorkflowTasks;
use App\Models\AdminNotification;
use App\Models\AssistantKnowledgeEntry;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\EmployeeContract;
use App\Models\EmployeeWarning;
use App\Models\MarketingTask;
use App\Models\SalesReservation;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class AdminConsoleSectionActionsTest extends BasePermissionTestCase
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
            'HR Oversight',
            'Marketing Oversight',
            'Inventory Oversight',
            'AI & Knowledge',
            'Requests & Workflow',
        ]);
    }

    #[Test]
    public function ai_admin_can_create_update_and_delete_knowledge_entries_from_filament(): void
    {
        $aiAdmin = $this->createGovernanceUser('ai_admin');

        $this->actingAs($aiAdmin);

        Livewire::test(CreateAssistantKnowledgeEntry::class)
            ->fillForm([
                'module' => 'assistant',
                'page_key' => 'admin-console',
                'title' => 'Admin Console Entry',
                'language' => 'ar',
                'priority' => 10,
                'is_active' => true,
                'tags' => ['admin'],
                'roles' => ['ai_admin'],
                'permissions' => ['manage-ai-knowledge'],
                'content_md' => 'Created from Filament.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = AssistantKnowledgeEntry::query()->where('title', 'Admin Console Entry')->firstOrFail();

        Livewire::test(EditAssistantKnowledgeEntry::class, ['record' => $entry->getRouteKey()])
            ->fillForm([
                'title' => 'Admin Console Entry Updated',
                'content_md' => 'Updated from Filament.',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('deleteEntry');

        $this->assertDatabaseMissing('assistant_knowledge_entries', [
            'id' => $entry->id,
        ]);
    }

    #[Test]
    public function workflow_admin_can_create_and_update_tasks_from_filament(): void
    {
        $workflowAdmin = $this->createGovernanceUser('workflow_admin');
        $assignee = $this->createSalesStaff([
            'is_active' => true,
            'email' => 'workflow-assignee@example.com',
        ]);

        $this->actingAs($workflowAdmin);

        Livewire::test(ListWorkflowTasks::class)
            ->callAction('createTask', data: [
                'task_name' => 'Governance Workflow Task',
                'assigned_to' => $assignee->id,
                'section' => 'workflow',
            ]);

        $task = Task::query()->where('task_name', 'Governance Workflow Task')->firstOrFail();

        Livewire::test(ListWorkflowTasks::class)
            ->callTableAction('markCompleted', $task->getKey())
            ->assertHasNoTableActionErrors();

        $task->refresh();

        $this->assertSame(Task::STATUS_COMPLETED, $task->status);
    }

    #[Test]
    public function hr_admin_can_create_update_and_delete_teams_from_filament(): void
    {
        $hrAdmin = $this->createGovernanceUser('hr_admin');

        $this->actingAs($hrAdmin);

        Livewire::test(CreateHrTeam::class)
            ->fillForm([
                'name' => 'Governance HR Team',
                'description' => 'Created from Filament admin.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $team = Team::query()->where('name', 'Governance HR Team')->firstOrFail();

        Livewire::test(EditHrTeam::class, ['record' => $team->getRouteKey()])
            ->fillForm([
                'name' => 'Governance HR Team Updated',
                'description' => 'Updated from Filament admin.',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('deleteTeam');

        $this->assertSoftDeleted($team->fresh());
    }

    #[Test]
    public function hr_admin_can_issue_warnings_and_create_contracts_from_filament(): void
    {
        $hrAdmin = $this->createGovernanceUser('hr_admin');
        $employee = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'governance-hr-employee@example.com',
        ]);

        $this->actingAs($hrAdmin);

        Livewire::test(ListEmployeeWarnings::class)
            ->callAction('issueWarning', data: [
                'user_id' => $employee->id,
                'type' => 'performance',
                'reason' => 'Governance warning',
                'details' => 'Issued from Filament.',
            ]);

        $warning = EmployeeWarning::query()->where('reason', 'Governance warning')->firstOrFail();

        Livewire::test(ListEmployeeContracts::class)
            ->callAction('createContract', data: [
                'user_id' => $employee->id,
                'job_title' => 'Account Executive',
                'department' => 'Sales',
                'salary' => 5000,
                'work_type' => 'full_time',
                'probation_period' => '90 days',
                'terms' => 'Governance terms',
                'benefits' => 'Health insurance',
                'start_date' => now()->toDateString(),
                'status' => 'draft',
            ]);

        $contract = EmployeeContract::query()->where('user_id', $employee->id)->latest('id')->firstOrFail();

        Livewire::test(ListEmployeeContracts::class)
            ->callTableAction('activateContract', $contract->getKey())
            ->callTableAction('generatePdf', $contract->getKey());

        $this->assertSame($employee->id, $warning->user_id);
        $this->assertSame('active', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->pdf_path);
    }

    #[Test]
    public function marketing_admin_can_create_complete_and_delete_marketing_tasks_from_filament(): void
    {
        $marketingAdmin = $this->createGovernanceUser('marketing_admin');
        $marketer = User::factory()->create([
            'type' => 'marketing',
            'is_active' => true,
        ]);
        $marketer->syncRolesFromType();
        $contract = Contract::factory()->create();

        $this->actingAs($marketingAdmin);

        Livewire::test(CreateMarketingTask::class)
            ->fillForm([
                'contract_id' => $contract->id,
                'task_name' => 'Governance Marketing Task',
                'marketer_id' => $marketer->id,
                'status' => 'pending',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = MarketingTask::query()->where('task_name', 'Governance Marketing Task')->firstOrFail();

        Livewire::test(ListMarketingTasks::class)
            ->callTableAction('markCompleted', $task->getKey())
            ->assertHasNoTableActionErrors();

        Livewire::test(EditMarketingTask::class, ['record' => $task->getRouteKey()])
            ->callAction('deleteTask');

        $this->assertDatabaseMissing('marketing_tasks', [
            'id' => $task->id,
        ]);
    }

    #[Test]
    public function sales_admin_can_confirm_and_cancel_reservations_from_filament(): void
    {
        $salesAdmin = $this->createGovernanceUser('sales_admin');
        $salesOwner = $this->createSalesStaff([
            'is_active' => true,
            'email' => 'sales-owner@example.com',
        ]);
        $contract = Contract::factory()->create();
        $unitOne = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
        ]);
        $unitTwo = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'reserved',
        ]);
        $confirmReservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unitOne->id,
            'marketing_employee_id' => $salesOwner->id,
            'status' => 'under_negotiation',
        ]);
        $cancelReservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unitTwo->id,
            'marketing_employee_id' => $salesOwner->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($salesAdmin);

        Livewire::test(ListSalesReservations::class)
            ->callTableAction('confirmReservation', $confirmReservation->getKey());

        Livewire::test(ListSalesReservations::class)
            ->callTableAction('cancelReservation', $cancelReservation->getKey());

        $this->assertSame('confirmed', $confirmReservation->fresh()->status);
        $this->assertSame('cancelled', $cancelReservation->fresh()->status);
    }

    #[Test]
    public function workflow_admin_can_send_and_mark_notifications_from_filament(): void
    {
        $workflowAdmin = $this->createGovernanceUser('workflow_admin');
        $recipient = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'workflow-recipient@example.com',
        ]);
        $adminRecipient = $this->createGovernanceUser('sales_admin');

        $this->actingAs($workflowAdmin);

        Livewire::test(ListUserNotifications::class)
            ->callAction('sendUserNotification', data: [
                'user_id' => $recipient->id,
                'message' => 'Governance private notification',
            ])
            ->callAction('sendPublicNotification', data: [
                'message' => 'Governance public notification',
            ]);

        $privateNotification = UserNotification::query()
            ->where('user_id', $recipient->id)
            ->where('message', 'Governance private notification')
            ->firstOrFail();

        Livewire::test(ListUserNotifications::class)
            ->callTableAction('markRead', $privateNotification->getKey());

        Livewire::test(ListAdminNotifications::class)
            ->callAction('sendAdminNotification', data: [
                'user_id' => $adminRecipient->id,
                'message' => 'Governance admin notification',
            ]);

        $adminNotification = AdminNotification::query()
            ->where('user_id', $adminRecipient->id)
            ->where('message', 'Governance admin notification')
            ->firstOrFail();

        Livewire::test(ListAdminNotifications::class)
            ->callTableAction('markRead', $adminNotification->getKey());

        $this->assertSame('read', $privateNotification->fresh()->status);
        $this->assertDatabaseHas('user_notifications', [
            'message' => 'Governance public notification',
            'user_id' => null,
        ]);
        $this->assertSame('read', $adminNotification->fresh()->status);
    }

    #[Test]
    public function projects_admin_and_inventory_admin_can_execute_contract_and_unit_admin_actions(): void
    {
        $projectsAdmin = $this->createGovernanceUser('projects_admin');
        $inventoryAdmin = $this->createGovernanceUser('inventory_admin');
        $pendingContract = Contract::factory()->create([
            'status' => 'pending',
        ]);

        $this->actingAs($projectsAdmin);

        Livewire::test(ListContracts::class)
            ->callTableAction('approveContract', $pendingContract->getKey())
            ->assertHasNoTableActionErrors();

        $unit = ContractUnit::factory()->create([
            'contract_id' => $pendingContract->id,
            'status' => 'available',
            'unit_number' => 'INV-900',
        ]);

        $this->actingAs($inventoryAdmin);

        Livewire::test(EditInventoryUnit::class, ['record' => $unit->getRouteKey()])
            ->fillForm([
                'unit_type' => 'Villa',
                'unit_number' => 'INV-901',
                'status' => 'reserved',
                'price' => 250000,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('deleteUnit');

        $this->assertSame('approved', $pendingContract->fresh()->status);
        $this->assertDatabaseMissing('contract_units', [
            'id' => $unit->id,
        ]);
    }

    protected function createGovernanceUser(string $role): User
    {
        $user = $this->createSuperAdmin([
            'is_active' => true,
            'email' => "{$role}-" . uniqid() . '@example.com',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
