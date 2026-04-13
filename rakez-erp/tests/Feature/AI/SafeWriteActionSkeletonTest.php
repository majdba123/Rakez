<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Models\AiAuditEntry;
use App\Models\Lead;
use App\Models\MarketingTask;
use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use App\Models\Task;
use App\Services\AI\Drafts\AssistantDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SafeWriteActionSkeletonTest extends TestCase
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
            'credit.bookings.view',
            'contracts.create',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_catalog_only_returns_actions_allowed_for_current_user(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.projects.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/write-actions/catalog');

        $response->assertOk()
            ->assertJsonPath('submit_mode', 'manual_only')
            ->assertJsonPath('execution_enabled', false);

        $keys = collect($response->json('actions'))->pluck('key')->all();

        $this->assertContains('task.create', $keys);
        $this->assertContains('lead.create', $keys);
        $this->assertContains('exclusive_project.request.create', $keys);
        $this->assertNotContains('marketing_task.create', $keys);
        $this->assertNotContains('sales_reservation.action.log', $keys);
        $this->assertNotContains('credit_booking.client_contact.log', $keys);
    }

    public function test_propose_task_create_returns_draft_backed_safe_write_envelope(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $draftService = Mockery::mock(AssistantDraftService::class);
        $draftService->shouldReceive('prepare')
            ->once()
            ->with($user, 'Create a task draft for Sara tomorrow', 'create_task_draft')
            ->andReturn([
                'status' => 'ready',
                'flow' => [
                    'key' => 'create_task_draft',
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                        'requires_explicit_user_submit' => true,
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'task_name' => 'Create campaign summary',
                    ],
                ],
                'validation_preview' => [
                    'is_valid' => true,
                    'errors' => [],
                ],
            ]);
        $this->app->instance(AssistantDraftService::class, $draftService);

        $response = $this->postJson('/api/ai/write-actions/propose', [
            'action_key' => 'task.create',
            'message' => 'Create a task draft for Sara tomorrow',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'proposed')
            ->assertJsonPath('data.stage', 'propose')
            ->assertJsonPath('data.action.key', 'task.create')
            ->assertJsonPath('data.controls.dry_run', true)
            ->assertJsonPath('data.controls.confirmation_required', true)
            ->assertJsonPath('data.controls.execution_enabled', false)
            ->assertJsonPath('data.result.proposal.flow.key', 'create_task_draft')
            ->assertJsonPath('data.result.proposal.validation_preview.is_valid', true);

        $this->assertDatabaseHas('ai_audit_trail', [
            'action' => 'safe_write_propose',
            'resource_type' => 'safe_write_action',
            'resource_id' => 'task.create',
        ]);
    }

    public function test_confirm_never_executes_even_for_draft_backed_actions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'task.create',
            'proposal' => [
                'flow' => [
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                    ],
                ],
            ],
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.execution_enabled', false)
            ->assertJsonPath('data.controls.intent_gate_allowed', false)
            ->assertJsonPath('data.controls.intent_gate_reason', 'intent_gate.global_disabled')
            ->assertJsonPath('data.controls.mutation_policy_allowed', false)
            ->assertJsonPath('data.controls.mutation_policy_reason', 'mutation_policy.unsupported_action')
            ->assertJsonPath('data.controls.confirmation_phrase_required', true)
            ->assertJsonPath('data.result.confirmation_boundary.manual_submit_required', true)
            ->assertJsonPath('data.result.handoff.path', '/api/tasks');

        $this->assertDatabaseCount((new Task)->getTable(), 0);
        $this->assertDatabaseCount((new MarketingTask)->getTable(), 0);
        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
    }

    public function test_confirm_requires_explicit_confirmation_phrase(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'task.create',
            'proposal' => [
                'flow' => [
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                    ],
                ],
            ],
            'confirmation_phrase' => 'confirm',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation_phrase']);
    }

    public function test_forbidden_action_is_wrapped_but_refused(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'contracts.create']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/propose', [
            'action_key' => 'contract.create',
            'payload' => [
                'project_name' => 'Unsafe Contract',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'refused')
            ->assertJsonPath('data.action.classification', 'forbidden_entirely')
            ->assertJsonPath('data.controls.execution_enabled', false);
    }

    public function test_propose_lead_create_uses_draft_backed_flow_without_hidden_write(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.projects.view']);
        Sanctum::actingAs($user);

        $draftService = Mockery::mock(AssistantDraftService::class);
        $draftService->shouldReceive('prepare')
            ->once()
            ->with($user, 'Create a lead draft for Falcon Project', 'create_lead_draft')
            ->andReturn([
                'status' => 'ready',
                'flow' => [
                    'key' => 'create_lead_draft',
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/marketing/leads',
                        'requires_explicit_user_submit' => true,
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'name' => 'Ahmed Prospect',
                    ],
                ],
                'validation_preview' => [
                    'is_valid' => true,
                    'errors' => [],
                ],
            ]);
        $this->app->instance(AssistantDraftService::class, $draftService);

        $response = $this->postJson('/api/ai/write-actions/propose', [
            'action_key' => 'lead.create',
            'message' => 'Create a lead draft for Falcon Project',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'proposed')
            ->assertJsonPath('data.action.key', 'lead.create')
            ->assertJsonPath('data.controls.execution_enabled', false)
            ->assertJsonPath('data.result.proposal.flow.key', 'create_lead_draft');

        $this->assertDatabaseCount((new Lead)->getTable(), 0);
    }

    public function test_credit_follow_up_action_is_draft_only_and_never_writes_on_confirm(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $path = '/api/credit/bookings/'.$reservation->id.'/actions';

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => [
                'flow' => [
                    'key' => 'log_credit_client_contact_draft',
                    'handoff' => [
                        'method' => 'POST',
                        'path' => $path,
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'sales_reservation_id' => $reservation->id,
                        'notes' => 'Follow-up',
                    ],
                ],
            ],
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.execution_enabled', false)
            ->assertJsonPath('data.controls.intent_gate_allowed', false)
            ->assertJsonPath('data.controls.intent_gate_reason', 'intent_gate.global_disabled')
            ->assertJsonPath('data.controls.mutation_policy_allowed', true)
            ->assertJsonPath('data.controls.mutation_policy_reason', 'mutation_policy.allowed')
            ->assertJsonPath('data.result.handoff.path', $path);

        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
    }

    public function test_confirm_credit_without_proposal_commit_token_is_rejected_when_gates_allow(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['credit_booking.client_contact.log'],
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);

        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $path = '/api/credit/bookings/'.$reservation->id.'/actions';

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => [
                'flow' => [
                    'key' => 'log_credit_client_contact_draft',
                    'handoff' => [
                        'method' => 'POST',
                        'path' => $path,
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'sales_reservation_id' => $reservation->id,
                        'notes' => 'Note',
                    ],
                ],
            ],
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.controls.execution_enabled', false)
            ->assertJsonPath('data.controls.intent_gate_allowed', true)
            ->assertJsonPath('data.controls.intent_gate_reason', 'intent_gate.allowed')
            ->assertJsonPath('data.controls.mutation_policy_allowed', true)
            ->assertJsonPath('data.controls.mutation_policy_reason', 'mutation_policy.allowed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.missing_commit_token');

        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
    }

    public function test_confirm_unknown_action_key_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'does.not.exist',
            'proposal' => [
                'flow' => [
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                    ],
                ],
            ],
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $response->assertForbidden();
    }

    public function test_confirm_other_draft_actions_remain_intent_blocked_when_candidate_gate_is_satisfied(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['credit_booking.client_contact.log'],
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'task.create',
            'proposal' => [
                'flow' => [
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                    ],
                ],
            ],
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.intent_gate_allowed', false)
            ->assertJsonPath('data.controls.intent_gate_reason', 'intent_gate.not_allowlisted')
            ->assertJsonPath('data.controls.mutation_policy_allowed', false)
            ->assertJsonPath('data.controls.mutation_policy_reason', 'mutation_policy.unsupported_action');
    }

    public function test_preview_returns_draft_only_envelope_and_records_audit_without_hidden_write(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'task.create',
            'proposal' => [
                'flow' => [
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'task_name' => 'Preview only draft',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'preview_ready')
            ->assertJsonPath('data.stage', 'preview')
            ->assertJsonPath('data.controls.execution_enabled', false)
            ->assertJsonPath('data.controls.confirmation_required', true)
            ->assertJsonPath('data.result.proposal.flow.handoff.path', '/api/tasks');

        $this->assertDatabaseHas('ai_audit_trail', [
            'action' => 'safe_write_preview',
            'resource_type' => 'safe_write_action',
            'resource_id' => 'task.create',
        ]);
        $this->assertDatabaseCount((new Task)->getTable(), 0);
        $this->assertDatabaseCount((new MarketingTask)->getTable(), 0);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
    }

    public function test_action_specific_permission_is_enforced(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/propose', [
            'action_key' => 'marketing_task.create',
            'message' => 'Create marketing task',
        ]);

        $response->assertForbidden();
    }

    public function test_propose_audit_summary_does_not_leak_raw_message_or_payload_values(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $draftService = Mockery::mock(AssistantDraftService::class);
        $draftService->shouldReceive('prepare')
            ->once()
            ->andReturn([
                'status' => 'ready',
                'flow' => [
                    'key' => 'create_task_draft',
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                        'requires_explicit_user_submit' => true,
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'task_name' => 'Sensitive follow-up',
                    ],
                ],
                'validation_preview' => [
                    'is_valid' => true,
                    'errors' => [],
                ],
            ]);
        $this->app->instance(AssistantDraftService::class, $draftService);

        $response = $this->postJson('/api/ai/write-actions/propose', [
            'action_key' => 'task.create',
            'message' => 'Call 0512345678 about VIP client',
            'payload' => [
                'notes' => 'Highly sensitive deal',
            ],
        ]);

        $response->assertOk();

        $audit = AiAuditEntry::query()->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('0512345678', (string) $audit->input_summary);
        $this->assertStringNotContainsString('Highly sensitive deal', (string) $audit->input_summary);
        $this->assertStringContainsString('message_present', (string) $audit->input_summary);
        $this->assertStringContainsString('payload_keys', (string) $audit->input_summary);
    }

    public function test_reject_records_audit_without_any_hidden_business_write(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/reject', [
            'action_key' => 'task.create',
            'proposal_id' => 99,
            'reason' => 'The human reviewer rejected the draft.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.controls.execution_enabled', false);

        $this->assertDatabaseHas('ai_audit_trail', [
            'action' => 'safe_write_reject',
            'resource_type' => 'safe_write_action',
            'resource_id' => 'task.create',
        ]);
        $this->assertDatabaseCount((new Task)->getTable(), 0);
        $this->assertDatabaseCount((new MarketingTask)->getTable(), 0);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
        $this->assertDatabaseCount((new SalesReservationAction)->getTable(), 0);
    }
}
