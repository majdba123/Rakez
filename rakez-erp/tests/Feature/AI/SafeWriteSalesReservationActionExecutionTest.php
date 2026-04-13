<?php

namespace Tests\Feature\AI;

use App\Models\AiAuditEntry;
use App\Models\SafeWriteExecutionOutcome;
use App\Models\SafeWriteProposalCommit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\SafeWrites\SalesReservationActionProposalBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SafeWriteSalesReservationActionExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'use-ai-assistant',
            'sales.reservations.view',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web');

        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['sales_reservation.action.log'],
            'safe_write_intent.actions.sales_reservation.action.log' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private function stableIdempotencyKey(array $proposal): string
    {
        $k = app(SalesReservationActionProposalBinder::class)->deriveStableIdempotencyKey($proposal);
        $this->assertNotNull($k);

        return $k;
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalForReservation(SalesReservation $reservation, string $actionType = 'lead_acquisition', string $notes = 'Note body'): array
    {
        $path = '/api/sales/reservations/'.$reservation->id.'/actions';

        return [
            'flow' => [
                'key' => 'log_reservation_action_draft',
                'handoff' => [
                    'method' => 'POST',
                    'path' => $path,
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => $reservation->id,
                    'action_type' => $actionType,
                    'notes' => $notes,
                ],
            ],
        ];
    }

    public function test_success_path_executes_for_marketing_owner(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'marketing_employee_id' => $owner->id,
        ]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation);
        $stable = $this->stableIdempotencyKey($proposal);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $preview->assertOk();
        $token = $preview->json('data.controls.proposal_commit_token');
        $this->assertNotEmpty($token);

        $confirm = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.controls.execution_enabled', true)
            ->assertJsonPath('data.result.action_type', 'lead_acquisition');

        $this->assertDatabaseHas('safe_write_execution_outcomes', [
            'user_id' => $owner->id,
            'action_key' => 'sales_reservation.action.log',
            'idempotency_key' => $stable,
        ]);

        $this->assertDatabaseHas('sales_reservation_actions', [
            'sales_reservation_id' => $reservation->id,
            'user_id' => $owner->id,
            'action_type' => 'lead_acquisition',
        ]);

        $audit = AiAuditEntry::query()->latest('id')->first();
        $this->assertNotNull($audit);
        $summary = json_decode((string) $audit->output_summary, true);
        $this->assertSame('lead_acquisition', $summary['execution']['action_type'] ?? null);
        $this->assertStringNotContainsString('Note body', (string) $audit->input_summary);
    }

    public function test_success_path_executes_for_admin_on_foreign_reservation(): void
    {
        $marketer = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
            'marketing_employee_id' => $marketer->id,
        ]);

        $admin = User::factory()->create();
        $admin->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $proposal = $this->proposalForReservation($reservation, 'closing', 'x');
        $stable = $this->stableIdempotencyKey($proposal);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.action_type', 'closing');

        $this->assertDatabaseHas('safe_write_execution_outcomes', [
            'user_id' => $admin->id,
            'action_key' => 'sales_reservation.action.log',
            'idempotency_key' => $stable,
        ]);
    }

    public function test_denied_by_intent_gate_when_action_flag_disabled(): void
    {
        config(['safe_write_intent.actions.sales_reservation.action.log' => false]);

        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
        ]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation);
        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.intent_gate_allowed', false);

        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_denied_by_mutation_policy_when_reservation_missing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($user);

        $badId = 99_999_999;
        $proposal = [
            'flow' => [
                'key' => 'log_reservation_action_draft',
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/sales/reservations/'.$badId.'/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => $badId,
                    'action_type' => 'lead_acquisition',
                    'notes' => 'n',
                ],
            ],
        ];

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.mutation_policy_allowed', false);

        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_denied_when_viewer_has_sales_reservations_view_but_is_not_owner_or_admin(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
        ]);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($viewer);

        $proposal = $this->proposalForReservation($reservation);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.mutation_policy_allowed', false);

        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_invalid_action_type_rejected_by_mutation_policy(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
        ]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation, 'not_a_real_enum');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.mutation_policy_allowed', false);
    }

    public function test_path_payload_mismatch_rejected_by_mutation_policy(): void
    {
        $owner = User::factory()->create();
        $reservationPayload = SalesReservation::factory()->create(['marketing_employee_id' => $owner->id]);
        $other = SalesReservation::factory()->create(['marketing_employee_id' => $owner->id]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservationPayload);
        $proposal['flow']['handoff']['path'] = '/api/sales/reservations/'.$other->id.'/actions';

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.mutation_policy_allowed', false);
    }

    public function test_proposal_change_after_preview_rejected_unknown_commit_token(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create(['marketing_employee_id' => $owner->id]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation, 'lead_acquisition', 'A');

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $tampered = $this->proposalForReservation($reservation, 'lead_acquisition', 'B');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $tampered,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.unknown_commit_token');

        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_confirm_rejects_when_commit_row_fingerprint_does_not_match_proposal(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create(['marketing_employee_id' => $owner->id]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        SafeWriteProposalCommit::query()
            ->where('commit_token', $token)
            ->where('user_id', $owner->id)
            ->update(['proposal_fingerprint' => str_repeat('c', 64)]);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.fingerprint_mismatch');

        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_idempotent_replay_safe(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create(['marketing_employee_id' => $owner->id]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation, 'persuasion');

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $payload = [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ];

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'executed');

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'idempotent_replay')
            ->assertJsonPath('data.controls.execution_replayed', true);

        $this->assertDatabaseCount('sales_reservation_actions', 1);
    }

    public function test_idempotency_conflict_when_stored_fingerprint_no_longer_matches_proposal(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create(['marketing_employee_id' => $owner->id]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation, 'lead_acquisition');

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'sales_reservation.action.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $payload = [
            'action_key' => 'sales_reservation.action.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ];

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'executed');

        $stable = $this->stableIdempotencyKey($proposal);
        SafeWriteExecutionOutcome::query()
            ->where('user_id', $owner->id)
            ->where('action_key', 'sales_reservation.action.log')
            ->where('idempotency_key', $stable)
            ->update(['proposal_fingerprint' => str_repeat('b', 64)]);

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'idempotency_conflict')
            ->assertJsonPath('data.result.binding_reason', 'idempotency.fingerprint_mismatch');
    }

    public function test_task_create_remains_non_executable_when_sales_intent_enabled(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'task.create',
            'proposal' => [
                'flow' => [
                    'key' => 'create_task_draft',
                    'handoff' => [
                        'method' => 'POST',
                        'path' => '/api/tasks',
                    ],
                ],
            ],
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled');
    }

    public function test_confirm_rejects_mismatched_client_idempotency_key(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create(['marketing_employee_id' => $owner->id]);
        $owner->givePermissionTo(['use-ai-assistant', 'sales.reservations.view']);
        Sanctum::actingAs($owner);

        $proposal = $this->proposalForReservation($reservation);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'sales_reservation.action.log',
            'idempotency_key' => 'client-wrong-key',
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }
}
