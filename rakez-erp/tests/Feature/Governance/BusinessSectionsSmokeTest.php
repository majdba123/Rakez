<?php

namespace Tests\Feature\Governance;

use App\Models\AdminNotification;
use App\Models\AiAuditEntry;
use App\Models\AiInteractionLog;
use App\Models\AssistantKnowledgeEntry;
use App\Models\EmployeePerformanceScore;
use App\Models\ProjectMedia;
use App\Models\Task;
use App\Models\UserNotification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class BusinessSectionsSmokeTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Accounting & Finance',
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
    public function erp_admin_pages_render_against_real_records_from_the_current_system_models(): void
    {
        $erpAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'erp-admin-smoke@example.com',
        ]);
        $erpAdmin->assignRole('erp_admin');

        $team = $this->createTeam(['created_by' => $erpAdmin->id]);

        $salesUser = $this->createSalesStaff(['team_id' => $team->id]);
        $marketingUser = $this->createMarketingStaff(['team_id' => $team->id]);
        $hrUser = $this->createHRStaff(['team_id' => $team->id]);

        $contract = \App\Models\Contract::factory()->create([
            'status' => 'completed',
            'project_name' => 'Smoke Contract',
        ]);

        $unit = \App\Models\ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'unit_number' => 'SMK-101',
            'status' => 'available',
        ]);

        \App\Models\SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $salesUser->id,
            'status' => 'confirmed',
            'client_name' => 'Smoke Client',
            'credit_status' => 'sold',
            'purchase_mechanism' => 'cash',
        ]);

        \App\Models\Deposit::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'client_name' => 'Smoke Client',
            'status' => 'confirmed',
        ]);

        \App\Models\CommissionDistribution::factory()->create([
            'user_id' => $salesUser->id,
            'status' => 'pending',
            'type' => 'lead_generation',
        ]);

        \App\Models\AccountingSalaryDistribution::factory()->create([
            'user_id' => $salesUser->id,
            'status' => 'approved',
        ]);

        \App\Models\ExclusiveProjectRequest::factory()->create([
            'requested_by' => $salesUser->id,
            'project_name' => 'Smoke Exclusive',
        ]);

        ProjectMedia::create([
            'contract_id' => $contract->id,
            'type' => 'image',
            'url' => 'https://example.com/smoke-image.jpg',
            'department' => 'montage',
        ]);

        \App\Models\SalesTarget::factory()->create([
            'contract_id' => $contract->id,
            'leader_id' => $salesUser->id,
            'marketer_id' => $marketingUser->id,
            'status' => 'in_progress',
        ]);

        \App\Models\SalesAttendanceSchedule::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $salesUser->id,
            'created_by' => $erpAdmin->id,
        ]);

        EmployeePerformanceScore::query()->create([
            'user_id' => $hrUser->id,
            'composite_score' => 88.5,
            'factor_scores' => ['quality' => 90],
            'strengths' => ['follow_up'],
            'weaknesses' => ['lateness'],
            'project_type_affinity' => ['residential'],
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ]);

        \App\Models\EmployeeWarning::factory()->create([
            'user_id' => $hrUser->id,
            'issued_by' => $erpAdmin->id,
            'reason' => 'Smoke warning',
        ]);

        \App\Models\EmployeeContract::factory()->create([
            'user_id' => $hrUser->id,
            'status' => 'active',
        ]);

        $marketingProject = \App\Models\MarketingProject::factory()->create([
            'contract_id' => $contract->id,
            'assigned_team_leader' => $marketingUser->id,
        ]);

        \App\Models\DeveloperMarketingPlan::factory()->create([
            'contract_id' => $contract->id,
        ]);

        \App\Models\EmployeeMarketingPlan::factory()->create([
            'marketing_project_id' => $marketingProject->id,
            'user_id' => $marketingUser->id,
        ]);

        \App\Models\MarketingTask::factory()->create([
            'contract_id' => $contract->id,
            'marketing_project_id' => $marketingProject->id,
            'marketer_id' => $marketingUser->id,
            'created_by' => $erpAdmin->id,
            'task_name' => 'Smoke Marketing Task',
        ]);

        \App\Models\Lead::factory()->create([
            'project_id' => $contract->id,
            'assigned_to' => $marketingUser->id,
            'name' => 'Smoke Lead',
            'status' => 'new',
        ]);

        AssistantKnowledgeEntry::query()->create([
            'module' => 'sales',
            'page_key' => 'reservations',
            'title' => 'Smoke Knowledge Entry',
            'content_md' => 'Smoke content',
            'tags' => ['smoke'],
            'roles' => [],
            'permissions' => [],
            'language' => 'ar',
            'is_active' => true,
            'priority' => 1,
            'updated_by' => $erpAdmin->id,
        ]);

        AiInteractionLog::query()->create([
            'user_id' => $erpAdmin->id,
            'session_id' => 'smoke-session',
            'correlation_id' => 'smoke-correlation',
            'section' => 'admin',
            'request_type' => 'qa',
            'model' => 'gpt-test',
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
            'latency_ms' => 120.5,
            'tool_calls_count' => 1,
            'had_error' => false,
            'created_at' => now(),
        ]);

        AiAuditEntry::query()->create([
            'user_id' => $erpAdmin->id,
            'correlation_id' => 'smoke-correlation',
            'action' => 'knowledge_view',
            'resource_type' => 'assistant_knowledge_entry',
            'resource_id' => 1,
            'input_summary' => 'input',
            'output_summary' => 'output',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        Task::query()->create([
            'task_name' => 'Smoke Workflow Task',
            'section' => 'sales',
            'team_id' => $team->id,
            'due_at' => now()->addDay(),
            'assigned_to' => $salesUser->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'created_by' => $erpAdmin->id,
        ]);

        AdminNotification::query()->create([
            'user_id' => $erpAdmin->id,
            'message' => 'Smoke Admin Notification',
            'status' => 'pending',
        ]);

        UserNotification::query()->create([
            'user_id' => $salesUser->id,
            'message' => 'Smoke User Notification',
            'status' => 'pending',
            'event_type' => 'smoke',
        ]);

        $this->actingAs($erpAdmin)->get('/admin/contracts')->assertOk()->assertSee('Smoke Contract');
        $this->actingAs($erpAdmin)->get('/admin/accounting-deposits')->assertOk()->assertSee('Smoke Client');
        $this->actingAs($erpAdmin)->get('/admin/sales-reservations')->assertOk()->assertSee('Smoke Client');
        $this->actingAs($erpAdmin)->get('/admin/hr-teams')->assertOk()->assertSee($team->name);
        $this->actingAs($erpAdmin)->get('/admin/marketing-projects-admin')->assertOk();
        $this->actingAs($erpAdmin)->get('/admin/inventory-units')->assertOk()->assertSee('SMK-101');
        $this->assertDatabaseHas('assistant_knowledge_entries', [
            'title' => 'Smoke Knowledge Entry',
        ]);
        $this->actingAs($erpAdmin)->get('/admin/assistant-knowledge-entries')->assertOk()->assertSee('Showing 1 result');
        $this->actingAs($erpAdmin)->get('/admin/workflow-tasks')->assertOk()->assertSee('Smoke Workflow Task');
    }
}
