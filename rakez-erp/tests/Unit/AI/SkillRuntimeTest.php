<?php

namespace Tests\Unit\AI;

use App\Models\Contract;
use App\Models\AiCall;
use App\Models\AiCallMessage;
use App\Models\AiCallScript;
use App\Models\Task;
use App\Models\CreditFinancingTracker;
use App\Models\Lead;
use App\Models\SalesReservation;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AI\Skills\SkillRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SkillRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('sales.dashboard.view', 'web');
        Permission::findOrCreate('sales.reservations.view', 'web');
        Permission::findOrCreate('ai-calls.manage', 'web');
        Permission::findOrCreate('contracts.view', 'web');
        Permission::findOrCreate('contracts.view_all', 'web');
        Permission::findOrCreate('credit.bookings.view', 'web');
        Permission::findOrCreate('credit.bookings.manage', 'web');
        Permission::findOrCreate('marketing.dashboard.view', 'web');
        Permission::findOrCreate('leads.view', 'web');
        Permission::findOrCreate('leads.view_all', 'web');
        Permission::findOrCreate('tasks.create', 'web');
        Permission::findOrCreate('notifications.view', 'web');
        Permission::findOrCreate('marketing.tasks.confirm', 'web');
        Permission::findOrCreate('marketing.tasks.view', 'web');
        Permission::findOrCreate('marketing.projects.view', 'web');
    }

    public function test_sales_skill_is_denied_without_section_capabilities(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.kpi_snapshot', []);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertTrue((bool) ($result['access_notes']['had_denied_request'] ?? false));
    }

    public function test_record_scoped_skill_requires_explicit_identifier(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('contracts.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'contracts.project_summary', []);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertNotEmpty($result['follow_up_questions']);
    }

    public function test_sales_kpi_skill_executes_successfully_with_required_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.kpi_snapshot', [
            'group_by' => 'day',
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('answer_markdown', $result);
    }

    public function test_contract_analysis_skill_returns_not_found_for_missing_record(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('contracts.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'contracts.project_analysis', [
            'project_id' => 999999,
        ]);

        $this->assertSame('not_found', $result['skill']['status']);
        $this->assertSame('row_scope.project_not_found', $result['access_notes']['reason']);
    }

    public function test_contract_analysis_skill_is_denied_when_record_is_outside_scope(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Contract::factory()->create([
            'user_id' => $owner->id,
        ]);

        $otherUser->givePermissionTo('use-ai-assistant');
        $otherUser->givePermissionTo('contracts.view');

        $result = app(SkillRuntime::class)->execute($otherUser->fresh(), 'contracts.project_analysis', [
            'project_id' => $project->id,
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('row_scope.project_forbidden', $result['access_notes']['reason']);
    }

    public function test_contract_analysis_skill_executes_for_owned_project(): void
    {
        $user = User::factory()->create();
        $project = Contract::factory()->create([
            'user_id' => $user->id,
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('contracts.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'contracts.project_analysis', [
            'project_id' => $project->id,
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame('analysis', $result['skill']['type']);
        $this->assertSame($project->id, $result['data']['id']);
    }

    public function test_marketing_analysis_skill_executes_successfully(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.dashboard.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.dashboard_analysis', [
            'report_type' => 'overview',
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame('analysis', $result['skill']['type']);
    }

    public function test_lead_summary_skill_redacts_contact_info_for_accessible_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'assigned_to' => $user->id,
            'contact_info' => '+966512345678',
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.dashboard.view');
        $user->givePermissionTo('leads.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.lead_summary', [
            'lead_id' => $lead->id,
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame('[REDACTED]', $result['data']['contact_info']);
    }

    public function test_lead_analysis_skill_is_denied_when_lead_is_outside_scope(): void
    {
        $assigned = User::factory()->create();
        $requester = User::factory()->create();
        $lead = Lead::factory()->create([
            'assigned_to' => $assigned->id,
        ]);

        $requester->givePermissionTo('use-ai-assistant');
        $requester->givePermissionTo('marketing.dashboard.view');
        $requester->givePermissionTo('leads.view');

        $result = app(SkillRuntime::class)->execute($requester->fresh(), 'marketing.lead_analysis', [
            'lead_id' => $lead->id,
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('row_scope.lead_forbidden', $result['access_notes']['reason']);
    }

    public function test_contract_status_summary_executes_for_owned_contract(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create([
            'user_id' => $user->id,
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('contracts.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'contracts.contract_status_summary', [
            'contract_id' => $contract->id,
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame($contract->id, $result['data']['id']);
    }

    public function test_sales_reservation_summary_executes_for_owned_reservation(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $user->id,
            'client_mobile' => '+966512345678',
            'client_iban' => 'SA1234567890123456789012',
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.reservations.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.reservation_summary', [
            'reservation_id' => $reservation->id,
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame('[REDACTED]', $result['data']['client_mobile']);
        $this->assertSame('[REDACTED]', $result['data']['client_iban']);
    }

    public function test_sales_reservation_analysis_is_denied_outside_scope(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
        ]);

        $requester->givePermissionTo('use-ai-assistant');
        $requester->givePermissionTo('sales.reservations.view');

        $result = app(SkillRuntime::class)->execute($requester->fresh(), 'sales.reservation_analysis', [
            'reservation_id' => $reservation->id,
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('row_scope.reservation_forbidden', $result['access_notes']['reason']);
    }

    public function test_credit_financing_summary_returns_not_found_without_tracker(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create();

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('credit.bookings.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.financing_summary', [
            'reservation_id' => $reservation->id,
        ]);

        $this->assertSame('not_found', $result['skill']['status']);
        $this->assertSame('row_scope.credit_tracker_not_found', $result['access_notes']['reason']);
    }

    public function test_credit_financing_summary_executes_for_existing_tracker(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'client_mobile' => '+966512345678',
        ]);
        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'assigned_to' => $user->id,
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('credit.bookings.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.financing_summary', [
            'reservation_id' => $reservation->id,
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame('[REDACTED]', $result['data']['client_mobile']);
        $this->assertSame($reservation->id, $result['data']['reservation_id']);
    }

    public function test_ai_calls_analytics_summary_executes_successfully(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('ai-calls.manage');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'ai_calls.analytics_summary', []);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertArrayHasKey('total_calls', $result['data']);
    }

    public function test_ai_call_summary_redacts_phone_number(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $script = AiCallScript::create([
            'name' => 'Lead Qualification',
            'target_type' => 'lead',
            'language' => 'ar',
            'questions' => [['key' => 'budget', 'text_ar' => 'What is your budget?']],
            'greeting_text' => 'Hello',
            'closing_text' => 'Bye',
            'max_retries_per_question' => 2,
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'customer_name' => $lead->name,
            'phone_number' => '+966512345678',
            'script_id' => $script->id,
            'status' => 'completed',
            'direction' => 'outbound',
            'attempt_number' => 1,
            'initiated_by' => $user->id,
            'call_summary' => 'Qualified lead',
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'My budget is 500k',
            'question_key' => 'budget',
            'timestamp_in_call' => 12,
            'created_at' => now(),
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('ai-calls.manage');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'ai_calls.call_summary', [
            'call_id' => $call->id,
        ]);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame('[REDACTED_PHONE]', $result['data']['call']['phone_number']);
    }

    public function test_workflow_queue_summary_returns_assigned_and_requested_tasks(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Task::create([
            'task_name' => 'Assigned task',
            'section' => 'sales',
            'due_at' => now()->addDay(),
            'assigned_to' => $user->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'created_by' => $otherUser->id,
        ]);

        Task::create([
            'task_name' => 'Requested task',
            'section' => 'marketing',
            'due_at' => now()->addDays(2),
            'assigned_to' => $otherUser->id,
            'status' => Task::STATUS_COMPLETED,
            'created_by' => $user->id,
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('tasks.create');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'workflow.queue_summary', []);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame(1, $result['data']['assigned_summary']['total']);
        $this->assertSame(1, $result['data']['requested_summary']['total']);
    }

    public function test_notifications_digest_returns_private_and_public_notifications(): void
    {
        $user = User::factory()->create();

        UserNotification::create([
            'user_id' => $user->id,
            'message' => 'Private notice',
            'status' => 'pending',
        ]);

        UserNotification::create([
            'user_id' => null,
            'message' => 'Public notice',
            'status' => 'pending',
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('notifications.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'notifications.digest', []);

        $this->assertSame('ok', $result['skill']['status']);
        $this->assertSame(1, $result['data']['private_notifications']['total']);
        $this->assertSame(1, $result['data']['public_notifications']['total']);
    }

    public function test_workflow_task_create_draft_returns_ready_with_valid_payload(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create(['type' => 'marketing']);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('tasks.create');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'workflow.task_create_draft', [
            'task_name' => 'Prepare launch brief',
            'assigned_to' => $assignee->id,
            'due_at' => now()->addDay()->toDateTimeString(),
            'section' => 'marketing',
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertSame('manual_only', $result['data']['draft']['submit_mode']);
        $this->assertTrue($result['data']['validation_preview']['is_valid']);
        $this->assertArrayHasKey('payload_preview', $result['data']);
        $this->assertSame([], $result['data']['resolved_scope']);
    }

    public function test_sales_followup_action_draft_requires_explicit_reservation_id(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.reservations.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.followup_action_draft', [
            'action_type' => 'closing',
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertContains('Provide `sales_reservation_id` to continue.', $result['follow_up_questions']);
    }

    public function test_credit_client_contact_draft_returns_ready_when_payload_is_valid(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create();

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('credit.bookings.view');
        $user->givePermissionTo('credit.bookings.manage');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.client_contact_draft', [
            'sales_reservation_id' => $reservation->id,
            'notes' => 'Client asked to be contacted after 4 PM.',
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertSame('/api/credit/bookings/'.$reservation->id.'/actions', $result['data']['flow']['handoff']['path']);
        $this->assertSame($reservation->id, $result['data']['resolved_scope']['record_id']);
        $this->assertSame($reservation->id, $result['data']['payload_preview']['sales_reservation_id']);
    }

    public function test_marketing_task_create_draft_returns_needs_input_without_required_fields(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.tasks.view');
        $user->givePermissionTo('marketing.tasks.confirm');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.task_create_draft', [
            'task_name' => 'Banner refresh',
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertNotEmpty($result['data']['missing_inputs']);
        $this->assertContains('contract_id', $result['data']['missing_inputs']);
    }

    public function test_marketing_lead_create_draft_redacts_contact_info_in_payload_preview(): void
    {
        $contract = Contract::factory()->create();
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.projects.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.lead_create_draft', [
            'name' => 'Lead A',
            'contact_info' => '0500000000',
            'project_id' => $contract->id,
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertSame([], $result['data']['resolved_scope']);
        $this->assertStringContainsString('REDACT', (string) ($result['data']['payload_preview']['contact_info'] ?? ''));
    }

    public function test_sales_followup_action_draft_denied_for_reservation_outside_scope(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
        ]);

        $other->givePermissionTo('use-ai-assistant');
        $other->givePermissionTo('sales.reservations.view');

        $result = app(SkillRuntime::class)->execute($other->fresh(), 'sales.followup_action_draft', [
            'sales_reservation_id' => $reservation->id,
            'action_type' => 'closing',
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertTrue($result['access_notes']['had_denied_request']);
    }

    public function test_sales_followup_action_draft_ready_for_owned_reservation(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $user->id,
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.reservations.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.followup_action_draft', [
            'sales_reservation_id' => $reservation->id,
            'action_type' => 'closing',
            'notes' => 'Call tomorrow.',
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertSame($reservation->id, $result['data']['resolved_scope']['record_id']);
        $this->assertSame('sales_reservation', $result['data']['resolved_scope']['record_type']);
    }

    public function test_sales_followup_action_draft_not_found_for_unknown_reservation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.reservations.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.followup_action_draft', [
            'sales_reservation_id' => 999999999,
            'action_type' => 'closing',
        ]);

        $this->assertSame('not_found', $result['skill']['status']);
    }

    public function test_credit_client_contact_draft_denied_without_manage_permission(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create();

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('credit.bookings.view');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.client_contact_draft', [
            'sales_reservation_id' => $reservation->id,
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('skill_gate.permissions', $result['access_notes']['reason'] ?? '');
    }
}
