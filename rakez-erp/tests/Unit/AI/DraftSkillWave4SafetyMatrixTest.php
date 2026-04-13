<?php

namespace Tests\Unit\AI;

use App\Models\Contract;
use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Skills\SkillRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Wave 4 draft-only safety: deterministic contract, gates, row scope, redaction, no persistence.
 */
class DraftSkillWave4SafetyMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'use-ai-assistant',
            'tasks.create',
            'marketing.tasks.view',
            'marketing.tasks.confirm',
            'marketing.projects.view',
            'sales.reservations.view',
            'credit.bookings.view',
            'credit.bookings.manage',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    /**
     * When DraftSkillHandler ran, payload_preview must mirror draft.payload; missing_inputs mirror validation_preview.missing_fields; execution disabled.
     *
     * @param  array<string, mixed>  $result
     */
    private function assertDraftHandlerContractWhenPresent(array $result): void
    {
        $data = $result['data'] ?? [];
        if (! isset($data['draft'])) {
            return;
        }

        $this->assertArrayHasKey('payload_preview', $data);
        $this->assertArrayHasKey('validation_preview', $data);
        $this->assertArrayHasKey('missing_inputs', $data);
        $this->assertSame($data['payload_preview'], $data['draft']['payload']);
        $this->assertSame($data['missing_inputs'], $data['validation_preview']['missing_fields']);
        $this->assertSame('manual_only', $data['draft']['submit_mode']);
        $this->assertTrue($data['draft']['requires_human_confirmation']);
        $this->assertFalse($data['confirmation_boundary']['assistant_execution_enabled']);
        $this->assertTrue($data['confirmation_boundary']['manual_submit_required']);
        $this->assertTrue($data['confirmation_boundary']['safe_write_confirm_required']);
        $this->assertIsArray($data['validation_preview']['errors']);
        $this->assertArrayHasKey('resolved_scope', $data);
        $this->assertIsArray($data['resolved_scope']);
    }

    // --- workflow.task_create_draft ---

    public function test_workflow_task_create_draft_ready_has_consistent_preview_and_does_not_create_tasks(): void
    {
        $beforeTasks = Task::query()->count();

        $user = User::factory()->create();
        $assignee = User::factory()->create(['type' => 'marketing']);
        $user->givePermissionTo(['use-ai-assistant', 'tasks.create']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'workflow.task_create_draft', [
            'task_name' => 'Safety brief',
            'assigned_to' => $assignee->id,
            'due_at' => now()->addDay()->toDateTimeString(),
            'section' => 'marketing',
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertSame('draft', $result['skill']['type']);
        $this->assertTrue($result['data']['validation_preview']['is_valid']);
        $this->assertSame([], $result['data']['validation_preview']['errors']);
        $this->assertDraftHandlerContractWhenPresent($result);
        $this->assertSame([], $result['data']['resolved_scope']);
        $this->assertSame($beforeTasks, Task::query()->count());
    }

    public function test_workflow_task_create_draft_needs_input_aligns_missing_inputs_and_validation_preview(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'tasks.create']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'workflow.task_create_draft', [
            'section' => 'marketing',
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertNotEmpty($result['data']['missing_inputs']);
        $this->assertFalse($result['data']['validation_preview']['is_valid']);
        $this->assertDraftHandlerContractWhenPresent($result);
    }

    public function test_workflow_task_create_draft_denied_when_workflow_section_capability_missing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'workflow.task_create_draft', [
            'task_name' => 'X',
            'assigned_to' => 1,
            'due_at' => now()->addDay()->toDateTimeString(),
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('section_gate.capabilities', $result['access_notes']['reason'] ?? '');
    }

    public function test_workflow_task_create_draft_denied_without_use_ai_assistant(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('tasks.create');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'workflow.task_create_draft', []);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('skill_gate.use_ai_assistant', $result['access_notes']['reason'] ?? '');
    }

    // --- marketing.task_create_draft ---

    public function test_marketing_task_create_draft_ready_has_consistent_preview(): void
    {
        $contract = Contract::factory()->create();
        $marketer = User::factory()->create();

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.tasks.view', 'marketing.tasks.confirm']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.task_create_draft', [
            'contract_id' => $contract->id,
            'task_name' => 'Design review',
            'marketer_id' => $marketer->id,
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertTrue($result['data']['validation_preview']['is_valid']);
        $this->assertSame([], $result['data']['validation_preview']['errors']);
        $this->assertDraftHandlerContractWhenPresent($result);
        $this->assertSame([], $result['data']['resolved_scope']);
    }

    public function test_marketing_task_create_draft_needs_input_contract_matches_skill_runtime(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.tasks.view', 'marketing.tasks.confirm']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.task_create_draft', [
            'task_name' => 'Incomplete',
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertNotEmpty($result['data']['missing_inputs']);
        $this->assertContains('contract_id', $result['data']['missing_inputs']);
        $this->assertSame($result['data']['missing_inputs'], $result['data']['validation_preview']['missing_fields']);
        $this->assertSame([], $result['data']['resolved_scope']);
        $this->assertDraftHandlerContractWhenPresent($result);
    }

    public function test_marketing_task_create_draft_denied_without_marketing_tasks_confirm_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.tasks.view']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.task_create_draft', [
            'contract_id' => 1,
            'task_name' => 'X',
            'marketer_id' => 1,
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('skill_gate.permissions', $result['access_notes']['reason'] ?? '');
    }

    public function test_marketing_task_create_draft_denied_when_marketing_tasks_section_requires_view_capability(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.tasks.confirm']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.task_create_draft', [
            'task_name' => 'Blocked by section gate',
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('section_gate.capabilities', $result['access_notes']['reason'] ?? '');
    }

    // --- marketing.lead_create_draft ---

    public function test_marketing_lead_create_draft_needs_input_when_required_fields_missing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.projects.view']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.lead_create_draft', [
            'name' => 'Partial lead',
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertNotEmpty($result['data']['missing_inputs']);
        $this->assertFalse($result['data']['validation_preview']['is_valid']);
        $this->assertDraftHandlerContractWhenPresent($result);
        $this->assertSame([], $result['data']['resolved_scope']);
    }

    public function test_marketing_lead_create_draft_denied_when_marketing_projects_section_not_granted(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.lead_create_draft', [
            'name' => 'N',
            'contact_info' => 'c',
            'project_id' => 1,
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('section_gate.capabilities', $result['access_notes']['reason'] ?? '');
    }

    public function test_marketing_lead_create_draft_surfaces_validation_preview_errors_for_nonexistent_project(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.projects.view']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'marketing.lead_create_draft', [
            'name' => 'Lead',
            'contact_info' => '0501111111',
            'project_id' => 999999999,
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertNotEmpty($result['data']['validation_preview']['errors']);
        $this->assertFalse($result['data']['validation_preview']['is_valid']);
        $this->assertDraftHandlerContractWhenPresent($result);
        $this->assertSame([], $result['data']['resolved_scope']);
    }

    // --- sales.followup_action_draft ---

    public function test_sales_followup_action_draft_denied_when_sales_reservations_section_not_granted(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.followup_action_draft', []);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('section_gate.capabilities', $result['access_notes']['reason'] ?? '');
    }

    public function test_sales_followup_action_draft_surfaces_validation_errors_for_invalid_action_type(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create(['marketing_employee_id' => $user->id]);
        $user->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);

        $beforeActions = SalesReservationAction::query()->count();

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.followup_action_draft', [
            'sales_reservation_id' => $reservation->id,
            'action_type' => 'not_a_canonical_action',
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertNotEmpty($result['data']['validation_preview']['errors']);
        $this->assertFalse($result['data']['validation_preview']['is_valid']);
        $this->assertDraftHandlerContractWhenPresent($result);
        $this->assertSame($beforeActions, SalesReservationAction::query()->count());
    }

    public function test_sales_followup_action_draft_redacts_sensitive_notes_in_payload_preview(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create(['marketing_employee_id' => $user->id]);
        $user->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'sales.followup_action_draft', [
            'sales_reservation_id' => $reservation->id,
            'action_type' => 'closing',
            'notes' => 'Call client at 0512345678 tomorrow.',
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertStringContainsString('REDACT', (string) ($result['data']['payload_preview']['notes'] ?? ''));
        $this->assertDraftHandlerContractWhenPresent($result);
    }

    // --- credit.client_contact_draft ---

    public function test_credit_client_contact_draft_not_found_for_unknown_reservation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.client_contact_draft', [
            'sales_reservation_id' => 999999999,
        ]);

        $this->assertSame('not_found', $result['skill']['status']);
        $this->assertSame('row_scope.reservation_not_found', $result['access_notes']['reason'] ?? '');
    }

    public function test_credit_client_contact_draft_row_scope_needs_input_without_reservation_id(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.client_contact_draft', [
            'notes' => 'Notes only',
        ]);

        $this->assertSame('needs_input', $result['skill']['status']);
        $this->assertArrayNotHasKey('draft', $result['data']);
        $this->assertSame('row_scope.credit_booking_reservation_id_required', $result['access_notes']['reason'] ?? '');
    }

    public function test_credit_client_contact_draft_ready_does_not_insert_sales_reservation_actions(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);

        $before = SalesReservationAction::query()->count();

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.client_contact_draft', [
            'sales_reservation_id' => $reservation->id,
            'notes' => 'Follow-up note',
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertSame($before, SalesReservationAction::query()->count());
        $this->assertSame('credit_booking', $result['data']['resolved_scope']['record_type']);
        $this->assertSame($reservation->id, $result['data']['resolved_scope']['record_id']);
        $this->assertDraftHandlerContractWhenPresent($result);
    }

    public function test_credit_client_contact_draft_denied_when_credit_bookings_section_requires_view_capability(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.manage']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.client_contact_draft', [
            'sales_reservation_id' => 1,
        ]);

        $this->assertSame('denied', $result['skill']['status']);
        $this->assertSame('section_gate.capabilities', $result['access_notes']['reason'] ?? '');
    }

    public function test_credit_client_contact_draft_redacts_phone_like_notes_in_preview(): void
    {
        $user = User::factory()->create();
        $reservation = SalesReservation::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);

        $result = app(SkillRuntime::class)->execute($user->fresh(), 'credit.client_contact_draft', [
            'sales_reservation_id' => $reservation->id,
            'notes' => '0512345678',
        ]);

        $this->assertSame('ready', $result['skill']['status']);
        $this->assertStringContainsString('REDACT', (string) ($result['data']['payload_preview']['notes'] ?? ''));
    }
}
