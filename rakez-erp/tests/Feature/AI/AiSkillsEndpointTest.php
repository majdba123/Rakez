<?php

namespace Tests\Feature\AI;

use App\Models\Contract;
use App\Models\AiCall;
use App\Models\AiCallMessage;
use App\Models\AiCallScript;
use App\Models\CreditFinancingTracker;
use App\Models\Lead;
use App\Models\SalesReservation;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiSkillsEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('sales.dashboard.view', 'web');
        Permission::findOrCreate('sales.reservations.view', 'web');
        Permission::findOrCreate('ai-calls.manage', 'web');
        Permission::findOrCreate('credit.bookings.view', 'web');
        Permission::findOrCreate('credit.bookings.manage', 'web');
        Permission::findOrCreate('marketing.dashboard.view', 'web');
        Permission::findOrCreate('contracts.view', 'web');
        Permission::findOrCreate('leads.view', 'web');
        Permission::findOrCreate('tasks.create', 'web');
        Permission::findOrCreate('notifications.view', 'web');
        Permission::findOrCreate('marketing.tasks.confirm', 'web');
        Permission::findOrCreate('marketing.tasks.view', 'web');
        Permission::findOrCreate('marketing.projects.view', 'web');
    }

    public function test_tools_chat_accepts_skill_key_execution_path(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'sales.kpi_snapshot',
            'skill_input' => [
                'group_by' => 'day',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.key', 'sales.kpi_snapshot')
            ->assertJsonPath('data.skill.status', 'ok')
            ->assertJsonPath('data.grounding.has_sources', true);
    }

    public function test_tools_chat_keeps_message_validation_when_no_skill_key_is_provided(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_tools_chat_returns_structured_denial_for_skill_gate_failures(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'sales.kpi_snapshot',
            'skill_input' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.key', 'sales.kpi_snapshot')
            ->assertJsonPath('data.skill.status', 'denied')
            ->assertJsonPath('data.access_notes.had_denied_request', true);
    }

    public function test_skills_catalog_returns_only_user_accessible_skills(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');
        Sanctum::actingAs($user->fresh());

        $response = $this->getJson('/api/ai/skills');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $skills = collect($response->json('data.skills'));
        $keys = $skills->pluck('skill_key')->all();

        $this->assertContains('sales.kpi_snapshot', $keys);
        $this->assertNotContains('marketing.dashboard_insights', $keys);
    }

    public function test_skills_catalog_supports_section_filter(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.dashboard.view');
        $user->givePermissionTo('marketing.dashboard.view');
        Sanctum::actingAs($user->fresh());

        $response = $this->getJson('/api/ai/skills?section=sales');

        $response->assertOk()
            ->assertJsonPath('data.section_filter', 'sales');

        $skills = collect($response->json('data.skills'));
        $sectionKeys = array_values(array_unique($skills->pluck('section_key')->all()));

        $this->assertSame(['sales'], $sectionKeys);
    }

    public function test_tools_chat_executes_analysis_skill_via_skill_runtime(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.dashboard.view');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'marketing.dashboard_analysis',
            'skill_input' => [
                'report_type' => 'overview',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.key', 'marketing.dashboard_analysis')
            ->assertJsonPath('data.skill.type', 'analysis')
            ->assertJsonPath('data.skill.status', 'ok');
    }

    public function test_tools_chat_returns_denied_for_project_analysis_outside_scope(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $project = Contract::factory()->create([
            'user_id' => $owner->id,
        ]);

        $requester->givePermissionTo('use-ai-assistant');
        $requester->givePermissionTo('contracts.view');
        Sanctum::actingAs($requester->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'contracts.project_analysis',
            'skill_input' => [
                'project_id' => $project->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'denied')
            ->assertJsonPath('data.access_notes.reason', 'row_scope.project_forbidden');
    }

    public function test_tools_chat_executes_lead_summary_skill_with_redacted_output(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'assigned_to' => $user->id,
            'contact_info' => '+966512345678',
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.dashboard.view');
        $user->givePermissionTo('leads.view');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'marketing.lead_summary',
            'skill_input' => [
                'lead_id' => $lead->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ok')
            ->assertJsonPath('data.data.contact_info', '[REDACTED]');
    }

    public function test_tools_chat_executes_sales_reservation_summary_with_redacted_output(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $user->id,
            'client_mobile' => '+966512345678',
            'client_iban' => 'SA1234567890123456789012',
        ]);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('sales.reservations.view');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'sales.reservation_summary',
            'skill_input' => [
                'reservation_id' => $reservation->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ok')
            ->assertJsonPath('data.data.client_mobile', '[REDACTED]')
            ->assertJsonPath('data.data.client_iban', '[REDACTED]');
    }

    public function test_tools_chat_executes_credit_financing_summary_with_redacted_output(): void
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
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'credit.financing_summary',
            'skill_input' => [
                'reservation_id' => $reservation->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ok')
            ->assertJsonPath('data.data.client_mobile', '[REDACTED]');
    }

    public function test_tools_chat_executes_ai_call_summary_with_redacted_output(): void
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
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'ai_calls.call_summary',
            'skill_input' => [
                'call_id' => $call->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ok')
            ->assertJsonPath('data.data.call.phone_number', '[REDACTED_PHONE]');
    }

    public function test_tools_chat_executes_workflow_queue_summary(): void
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

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('tasks.create');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'workflow.queue_summary',
            'skill_input' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ok')
            ->assertJsonPath('data.data.assigned_summary.total', 1);
    }

    public function test_tools_chat_executes_notifications_digest(): void
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
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'notifications.digest',
            'skill_input' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ok')
            ->assertJsonPath('data.data.private_notifications.total', 1)
            ->assertJsonPath('data.data.public_notifications.total', 1);
    }

    public function test_tools_chat_executes_workflow_task_create_draft(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create(['type' => 'marketing']);

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('tasks.create');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'workflow.task_create_draft',
            'skill_input' => [
                'task_name' => 'Prepare launch brief',
                'assigned_to' => $assignee->id,
                'due_at' => now()->addDay()->toDateTimeString(),
                'section' => 'marketing',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ready')
            ->assertJsonPath('data.data.draft.submit_mode', 'manual_only');
    }

    public function test_tools_chat_executes_credit_client_contact_draft(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create();

        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('credit.bookings.view');
        $user->givePermissionTo('credit.bookings.manage');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'credit.client_contact_draft',
            'skill_input' => [
                'sales_reservation_id' => $reservation->id,
                'notes' => 'Client asked to be contacted after 4 PM.',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ready')
            ->assertJsonPath('data.data.flow.handoff.path', '/api/credit/bookings/'.$reservation->id.'/actions');
    }

    public function test_tools_chat_marketing_task_create_draft_needs_input(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.tasks.view');
        $user->givePermissionTo('marketing.tasks.confirm');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'marketing.task_create_draft',
            'skill_input' => [
                'task_name' => 'Incomplete draft',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'needs_input');
    }

    public function test_tools_chat_marketing_lead_create_draft_ready(): void
    {
        $contract = Contract::factory()->create();
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('marketing.projects.view');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'marketing.lead_create_draft',
            'skill_input' => [
                'name' => 'Lead A',
                'contact_info' => '0500000000',
                'project_id' => $contract->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skill.status', 'ready')
            ->assertJsonPath('data.data.payload_preview.project_id', $contract->id);
    }

    public function test_tools_chat_sales_followup_draft_denied_outside_scope(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
        ]);

        $other->givePermissionTo('use-ai-assistant');
        $other->givePermissionTo('sales.reservations.view');
        Sanctum::actingAs($other->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'sales.followup_action_draft',
            'skill_input' => [
                'sales_reservation_id' => $reservation->id,
                'action_type' => 'closing',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.skill.status', 'denied');
    }

    public function test_tools_chat_workflow_task_create_draft_denied_without_tasks_create_section_capability(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'workflow.task_create_draft',
            'skill_input' => ['task_name' => 'X'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.skill.status', 'denied')
            ->assertJsonPath('data.access_notes.reason', 'section_gate.capabilities');
    }

    public function test_tools_chat_marketing_lead_create_draft_denied_without_marketing_projects_capability(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'marketing.lead_create_draft',
            'skill_input' => ['name' => 'A', 'contact_info' => '0500000000', 'project_id' => 1],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.skill.status', 'denied')
            ->assertJsonPath('data.access_notes.reason', 'section_gate.capabilities');
    }

    public function test_tools_chat_credit_client_contact_draft_not_found_unknown_reservation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'credit.client_contact_draft',
            'skill_input' => ['sales_reservation_id' => 999999999],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.skill.status', 'not_found')
            ->assertJsonPath('data.access_notes.reason', 'row_scope.reservation_not_found');
    }

    public function test_tools_chat_ready_draft_exposes_manual_only_and_matching_payload_preview(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create(['type' => 'marketing']);
        $user->givePermissionTo(['use-ai-assistant', 'tasks.create']);
        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/ai/tools/chat', [
            'skill_key' => 'workflow.task_create_draft',
            'skill_input' => [
                'task_name' => 'Endpoint contract check',
                'assigned_to' => $assignee->id,
                'due_at' => now()->addDay()->toDateTimeString(),
                'section' => 'marketing',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.skill.status', 'ready')
            ->assertJsonPath('data.data.draft.submit_mode', 'manual_only')
            ->assertJsonPath('data.data.confirmation_boundary.assistant_execution_enabled', false)
            ->assertJsonPath('data.data.payload_preview.task_name', 'Endpoint contract check');
    }
}
