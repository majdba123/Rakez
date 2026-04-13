<?php

namespace Tests\Feature\AI;

use App\Models\Contract;
use App\Models\AiAuditEntry;
use App\Models\Lead;
use App\Models\MarketingTask;
use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use App\Models\Team;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Drafts\AssistantDraftUnderstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssistantDraftPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'use-ai-assistant',
            'marketing.tasks.confirm',
            'marketing.projects.view',
            'sales.reservations.view',
            'credit.bookings.manage',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_catalog_returns_supported_and_blocked_flows(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.tasks.confirm', 'marketing.projects.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/drafts/flows');

        $response->assertOk()
            ->assertJsonPath('data.submit_mode', 'manual_only');

        $supported = collect($response->json('data.supported_flows'))->pluck('key')->all();
        $this->assertContains('create_task_draft', $supported);
        $this->assertContains('create_marketing_task_draft', $supported);
        $this->assertContains('create_lead_draft', $supported);
    }

    public function test_prepare_task_draft_resolves_assignee_and_builds_ready_preview(): void
    {
        $team = Team::factory()->create();
        $assignee = User::factory()->create([
            'name' => 'Sara Marketing',
            'type' => 'marketing',
            'team_id' => $team->id,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'create_task_draft',
            'confidence' => 'high',
            'explanation' => 'The user wants to prepare a generic task draft.',
            'missing_fields' => [],
            'ambiguities' => [],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => 'Prepare campaign summary',
                'section' => null,
                'due_at' => '2026-04-10 14:30',
                'assigned_to_reference' => 'Sara Marketing',
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => null,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => null,
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a task for Sara Marketing to prepare campaign summary by 2026-04-10 14:30',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.flow.key', 'create_task_draft')
            ->assertJsonPath('data.draft.payload.task_name', 'Prepare campaign summary')
            ->assertJsonPath('data.draft.payload.assigned_to', $assignee->id)
            ->assertJsonPath('data.draft.payload.section', 'marketing')
            ->assertJsonPath('data.draft.payload.team_id', $team->id)
            ->assertJsonPath('data.validation_preview.is_valid', true)
            ->assertJsonPath('data.flow.handoff.path', '/api/tasks');

        $this->assertDatabaseCount((new Task)->getTable(), 0);
        $this->assertDatabaseCount((new MarketingTask)->getTable(), 0);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
    }

    public function test_prepare_lead_draft_resolves_project_and_optional_assignee_without_hidden_write(): void
    {
        $contract = Contract::factory()->create(['project_name' => 'Falcon Project']);
        $assignee = User::factory()->create([
            'name' => 'Nora Marketer',
            'type' => 'marketing',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.projects.view']);
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'create_lead_draft',
            'confidence' => 'high',
            'explanation' => 'The user wants to prepare a lead draft.',
            'missing_fields' => [],
            'ambiguities' => [],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => null,
                'section' => null,
                'due_at' => null,
                'lead_name' => 'Ahmed Prospect',
                'lead_contact_info' => '0500000000',
                'lead_source' => 'landing page',
                'lead_status' => 'new',
                'lead_notes' => 'Asked for evening callback',
                'assigned_to_reference' => 'Nora Marketer',
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'project_reference' => 'Falcon Project',
                'project_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => null,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => null,
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a lead draft for Ahmed Prospect on Falcon Project assigned to Nora Marketer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.flow.key', 'create_lead_draft')
            ->assertJsonPath('data.flow.safe_write_action_key', 'lead.create')
            ->assertJsonPath('data.draft.payload.name', 'Ahmed Prospect')
            ->assertJsonPath('data.draft.payload.project_id', $contract->id)
            ->assertJsonPath('data.draft.payload.assigned_to', $assignee->id)
            ->assertJsonPath('data.validation_preview.is_valid', true)
            ->assertJsonPath('data.confirmation_boundary.assistant_execution_enabled', false)
            ->assertJsonPath('data.confirmation_boundary.manual_submit_required', true)
            ->assertJsonPath('data.flow.handoff.path', '/api/marketing/leads');

        $warnings = $response->json('data.validation_preview.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertDatabaseHas('ai_audit_trail', [
            'user_id' => $user->id,
            'action' => 'assistant_draft_prepare',
        ]);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
        $this->assertDatabaseCount((new Task)->getTable(), 0);
        $this->assertDatabaseCount((new MarketingTask)->getTable(), 0);
        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
    }

    public function test_prepare_marketing_task_draft_resolves_project_and_marketer(): void
    {
        $contract = Contract::factory()->create(['project_name' => 'Alpha Campaign Project']);
        $marketer = User::factory()->create([
            'name' => 'Mona Marketer',
            'type' => 'marketing',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.tasks.confirm']);
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'create_marketing_task_draft',
            'confidence' => 'high',
            'explanation' => 'The user wants a marketing task draft.',
            'missing_fields' => [],
            'ambiguities' => [],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => 'Publish teaser design',
                'section' => null,
                'due_at' => null,
                'assigned_to_reference' => null,
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => 'Alpha Campaign Project',
                'contract_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => 'Mona Marketer',
                'marketer_id' => null,
                'participating_marketers_count' => 2,
                'design_link' => 'https://example.com/design',
                'design_number' => 'D-42',
                'design_description' => 'Arabic teaser for launch week',
                'status' => 'new',
                'reservation_reference' => null,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => null,
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a marketing task for Mona Marketer on Alpha Campaign Project',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.flow.key', 'create_marketing_task_draft')
            ->assertJsonPath('data.draft.payload.contract_id', $contract->id)
            ->assertJsonPath('data.draft.payload.marketer_id', $marketer->id)
            ->assertJsonPath('data.validation_preview.is_valid', true)
            ->assertJsonPath('data.flow.handoff.path', '/api/marketing/tasks');
    }

    public function test_prepare_reservation_action_draft_requires_explicit_reservation_id(): void
    {
        $reservation = SalesReservation::factory()->create();

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'log_reservation_action_draft',
            'confidence' => 'high',
            'explanation' => 'The user wants to prepare a reservation action draft.',
            'missing_fields' => [],
            'ambiguities' => [],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => null,
                'section' => null,
                'due_at' => null,
                'assigned_to_reference' => null,
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => (string) $reservation->id,
                'sales_reservation_id' => null,
                'action_type' => 'إقناع',
                'notes' => 'Client requested another callback tomorrow.',
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => "Add a persuasion follow-up note to reservation {$reservation->id}",
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.draft.payload.sales_reservation_id', $reservation->id)
            ->assertJsonPath('data.draft.payload.action_type', 'persuasion')
            ->assertJsonPath('data.validation_preview.is_valid', true)
            ->assertJsonPath('data.flow.handoff.path', "/api/sales/reservations/{$reservation->id}/actions");
    }

    public function test_prepare_credit_follow_up_draft_requires_explicit_reservation_id_and_stays_append_only(): void
    {
        $reservation = SalesReservation::factory()->create();

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'log_credit_client_contact_draft',
            'confidence' => 'high',
            'explanation' => 'The user wants a low-risk credit follow-up draft.',
            'missing_fields' => [],
            'ambiguities' => [],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => null,
                'section' => null,
                'due_at' => null,
                'lead_name' => null,
                'lead_contact_info' => null,
                'lead_source' => null,
                'lead_status' => null,
                'lead_notes' => null,
                'assigned_to_reference' => null,
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'project_reference' => null,
                'project_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => (string) $reservation->id,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => 'Client asked to call back after 5 PM.',
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => "Prepare a credit follow-up note for reservation {$reservation->id}",
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.flow.key', 'log_credit_client_contact_draft')
            ->assertJsonPath('data.flow.safe_write_action_key', 'credit_booking.client_contact.log')
            ->assertJsonPath('data.draft.payload.sales_reservation_id', $reservation->id)
            ->assertJsonPath('data.validation_preview.is_valid', true)
            ->assertJsonPath('data.flow.handoff.path', "/api/credit/bookings/{$reservation->id}/actions")
            ->assertJsonPath('data.confirmation_boundary.assistant_execution_enabled', false);

        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
    }

    public function test_prepare_task_draft_surfaces_assignee_ambiguity(): void
    {
        User::factory()->create(['name' => 'Same Name', 'type' => 'marketing']);
        User::factory()->create(['name' => 'Same Name', 'type' => 'marketing']);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'create_task_draft',
            'confidence' => 'medium',
            'explanation' => 'The user wants a task draft but the assignee is ambiguous.',
            'missing_fields' => [],
            'ambiguities' => ['assignee'],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => 'Prepare weekly report',
                'section' => null,
                'due_at' => '2026-04-10 10:00',
                'assigned_to_reference' => 'Same Name',
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => null,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => null,
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a task for Same Name to prepare the weekly report',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'needs_input');

        $this->assertNotEmpty($response->json('data.entity_confirmation.ambiguous'));
    }

    public function test_prepare_lead_draft_surfaces_project_ambiguity_without_hidden_write(): void
    {
        Contract::factory()->create(['project_name' => 'Duplicate Project']);
        Contract::factory()->create(['project_name' => 'Duplicate Project']);

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.projects.view']);
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'create_lead_draft',
            'confidence' => 'medium',
            'explanation' => 'The user wants a lead draft but the project is ambiguous.',
            'missing_fields' => [],
            'ambiguities' => ['project'],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => null,
                'section' => null,
                'due_at' => null,
                'lead_name' => 'Ambiguous Lead',
                'lead_contact_info' => '0500000000',
                'lead_source' => 'referral',
                'lead_status' => 'new',
                'lead_notes' => null,
                'assigned_to_reference' => null,
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'project_reference' => 'Duplicate Project',
                'project_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => null,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => null,
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a lead draft on Duplicate Project',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'needs_input');

        $this->assertNotEmpty($response->json('data.entity_confirmation.ambiguous'));
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
    }

    public function test_prepare_refuses_blocked_contract_write_request(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'refused',
            'flow_key' => null,
            'confidence' => 'high',
            'explanation' => 'The user is asking for a blocked contract write.',
            'missing_fields' => [],
            'ambiguities' => [],
            'refusal_reason' => 'Contract creation and modification are forbidden in draft-only mode.',
            'raw_slots' => [
                'task_name' => null,
                'section' => null,
                'due_at' => null,
                'assigned_to_reference' => null,
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => null,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => null,
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a new contract for the user and submit it now',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'refused')
            ->assertJsonPath('data.refusal.category', 'blocked_write');

        $audit = AiAuditEntry::query()->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('assistant_draft_prepare', $audit->action);
    }

    public function test_prepare_task_draft_reports_missing_fields_without_any_hidden_write(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->mockUnderstanding([
            'outcome' => 'draft_supported',
            'flow_key' => 'create_task_draft',
            'confidence' => 'medium',
            'explanation' => 'The user wants a task draft but required fields are missing.',
            'missing_fields' => ['task_name'],
            'ambiguities' => [],
            'refusal_reason' => null,
            'raw_slots' => [
                'task_name' => null,
                'section' => 'marketing',
                'due_at' => null,
                'assigned_to_reference' => null,
                'assigned_to' => null,
                'team_id' => null,
                'contract_reference' => null,
                'contract_id' => null,
                'marketing_project_id' => null,
                'marketer_reference' => null,
                'marketer_id' => null,
                'participating_marketers_count' => null,
                'design_link' => null,
                'design_number' => null,
                'design_description' => null,
                'status' => null,
                'reservation_reference' => null,
                'sales_reservation_id' => null,
                'action_type' => null,
                'notes' => null,
            ],
        ]);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a task draft for tomorrow',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'needs_input')
            ->assertJsonPath('data.draft.submit_mode', 'manual_only')
            ->assertJsonPath('data.draft.requires_human_confirmation', true)
            ->assertJsonPath('data.validation_preview.is_valid', false);

        $this->assertContains('task_name', $response->json('data.validation_preview.missing_fields'));
        $this->assertDatabaseCount((new Task)->getTable(), 0);
        $this->assertDatabaseCount((new MarketingTask)->getTable(), 0);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
    }

    public function test_requested_flow_without_permission_is_refused_without_privilege_escalation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/drafts/prepare', [
            'message' => 'Create a marketing task draft',
            'flow' => 'create_marketing_task_draft',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'refused')
            ->assertJsonPath('data.refusal.category', 'not_allowed');

        $this->assertDatabaseCount((new MarketingTask)->getTable(), 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mockUnderstanding(array $payload): void
    {
        $mock = Mockery::mock(AssistantDraftUnderstandingService::class);
        $mock->shouldReceive('understand')
            ->once()
            ->andReturn($payload);

        $this->app->instance(AssistantDraftUnderstandingService::class, $mock);
    }
}
