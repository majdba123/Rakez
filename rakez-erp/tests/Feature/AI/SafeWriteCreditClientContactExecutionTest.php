<?php

namespace Tests\Feature\AI;

use App\Models\AiAuditEntry;
use App\Models\SafeWriteExecutionOutcome;
use App\Models\SafeWriteProposalCommit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\SafeWrites\CreditClientContactProposalBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SafeWriteCreditClientContactExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'use-ai-assistant',
            'credit.bookings.view',
            'credit.bookings.manage',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['credit_booking.client_contact.log'],
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private function stableIdempotencyKey(array $proposal): string
    {
        $k = app(CreditClientContactProposalBinder::class)->deriveStableIdempotencyKey($proposal);
        $this->assertNotNull($k);

        return $k;
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalForReservation(SalesReservation $reservation, string $notes = 'Contact note'): array
    {
        $path = '/api/credit/bookings/'.$reservation->id.'/actions';

        return [
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
                    'notes' => $notes,
                ],
            ],
        ];
    }

    public function test_success_path_executes_without_client_idempotency_key(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $stable = $this->stableIdempotencyKey($proposal);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);

        $preview->assertOk();
        $token = $preview->json('data.controls.proposal_commit_token');
        $this->assertNotEmpty($token);

        $confirm = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.controls.execution_enabled', true)
            ->assertJsonPath('data.controls.execution_replayed', false);

        $this->assertDatabaseCount('sales_reservation_actions', 1);
        $this->assertDatabaseHas('safe_write_execution_outcomes', [
            'user_id' => $user->id,
            'action_key' => 'credit_booking.client_contact.log',
            'idempotency_key' => $stable,
        ]);

        $audit = AiAuditEntry::query()->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('safe_write_confirm', $audit->action);
        $summary = json_decode((string) $audit->output_summary, true);
        $this->assertSame('executed', $summary['status'] ?? null);
        $this->assertArrayHasKey('execution', $summary);
        $this->assertStringNotContainsString('Contact note', (string) $audit->input_summary);
    }

    public function test_confirm_rejects_mismatched_client_idempotency_key(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'idempotency_key' => 'client-wrong-key-not-derived-from-proposal',
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_preview_rejects_mismatched_client_idempotency_key(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);

        $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'idempotency_key' => 'wrong',
            'proposal' => $proposal,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_confirm_rejects_non_empty_idempotency_when_proposal_invalid_for_binding(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'idempotency_key' => 'sw_cc:some',
            'proposal' => [
                'flow' => ['key' => 'wrong_flow'],
                'draft' => ['payload' => []],
            ],
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_confirm_fails_when_handoff_path_reservation_id_mismatches_payload(): void
    {
        $reservationPayload = SalesReservation::factory()->create(['status' => 'confirmed']);
        $otherReservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservationPayload);
        $proposal['flow']['handoff']['path'] = '/api/credit/bookings/'.$otherReservation->id.'/actions';

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.handoff_path_reservation_mismatch');

        $audit = AiAuditEntry::query()->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('safe_write_confirm', $audit->action);
        $summary = json_decode((string) $audit->output_summary, true);
        $this->assertSame('proposal_binding.handoff_path_reservation_mismatch', $summary['execution']['reason'] ?? null);
    }

    public function test_preview_does_not_issue_commit_token_when_path_payload_reservation_mismatch(): void
    {
        $reservationPayload = SalesReservation::factory()->create(['status' => 'confirmed']);
        $otherReservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservationPayload);
        $proposal['flow']['handoff']['path'] = '/api/credit/bookings/'.$otherReservation->id.'/actions';

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);

        $preview->assertOk();
        $this->assertEmpty($preview->json('data.controls.proposal_commit_token'));
    }

    public function test_denied_when_intent_gate_disabled(): void
    {
        config(['safe_write_intent.enabled' => false]);

        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $confirm = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.intent_gate_allowed', false);
        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_denied_when_mutation_policy_fails_missing_reservation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $badId = 99_999_999;
        $proposal = [
            'flow' => [
                'key' => 'log_credit_client_contact_draft',
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/credit/bookings/'.$badId.'/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => $badId,
                    'notes' => 'x',
                ],
            ],
        ];

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $confirm = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.mutation_policy_allowed', false);
        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_denied_for_missing_credit_permission(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $this->proposalForReservation($reservation),
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $response->assertForbidden();
    }

    public function test_tampered_proposal_fails_binding(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation, 'Original');

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $tampered = $this->proposalForReservation($reservation, 'Tampered');

        $confirm = $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $tampered,
            'confirmation_phrase' => 'confirm_draft_only',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.unknown_commit_token');

        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_confirm_rejects_when_commit_row_fingerprint_does_not_match_proposal(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation, 'Bound notes');

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        SafeWriteProposalCommit::query()
            ->where('commit_token', $token)
            ->where('user_id', $user->id)
            ->update(['proposal_fingerprint' => str_repeat('c', 64)]);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.fingerprint_mismatch');

        $this->assertDatabaseCount('sales_reservation_actions', 0);
    }

    public function test_preview_exposes_same_stable_idempotency_key_for_identical_proposals(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $stable = $this->stableIdempotencyKey($proposal);

        $a = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->assertOk();

        $b = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->assertOk();

        $this->assertSame($stable, $a->json('data.controls.idempotency_key'));
        $this->assertSame($a->json('data.controls.idempotency_key'), $b->json('data.controls.idempotency_key'));
    }

    public function test_idempotency_conflict_when_outcome_fingerprint_no_longer_matches_proposal(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $payload = [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ];

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'executed');

        $stable = $this->stableIdempotencyKey($proposal);
        SafeWriteExecutionOutcome::query()
            ->where('user_id', $user->id)
            ->where('action_key', 'credit_booking.client_contact.log')
            ->where('idempotency_key', $stable)
            ->update(['proposal_fingerprint' => str_repeat('b', 64)]);

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'idempotency_conflict')
            ->assertJsonPath('data.result.binding_reason', 'idempotency.fingerprint_mismatch');
    }

    public function test_idempotent_replay_does_not_duplicate_write(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $payload = [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ];

        $first = $this->postJson('/api/ai/write-actions/confirm', $payload);
        $first->assertOk()->assertJsonPath('data.status', 'executed');

        $second = $this->postJson('/api/ai/write-actions/confirm', $payload);
        $second->assertOk()
            ->assertJsonPath('data.status', 'idempotent_replay')
            ->assertJsonPath('data.controls.execution_replayed', true)
            ->assertJsonPath('data.controls.execution_enabled', false);

        $this->assertDatabaseCount('sales_reservation_actions', 1);
    }

    public function test_confirm_accepts_matching_client_idempotency_key_equal_to_derived(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $stable = $this->stableIdempotencyKey($proposal);

        $preview = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ]);
        $token = $preview->json('data.controls.proposal_commit_token');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'idempotency_key' => $stable,
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'executed');
    }

    public function test_task_create_remains_execution_disabled_when_intent_allows_credit_only(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/write-actions/confirm', [
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
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled');
    }
}
