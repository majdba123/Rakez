<?php

namespace Tests\Feature\AI;

use App\Models\AiAuditEntry;
use App\Models\SafeWriteExecutionOutcome;
use App\Models\SafeWriteProposalCommit;
use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use App\Models\User;
use App\Services\AI\SafeWrites\CreditClientContactProposalBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Adversarial / replay / safety tests for credit_booking.client_contact.log confirm only.
 *
 * Concurrency note: PHPUnit uses an in-process SQLite database and sequential HTTP calls.
 * True parallel HTTP races (two simultaneous confirms) are not exercised here; the executor
 * mitigates same-reservation double-submit by taking a row lock on {@see SalesReservation}
 * before re-checking idempotency outcomes and writing. Cross-process locking is DB-backed.
 */
class SafeWriteCreditClientContactAdversarialTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function proposalForReservation(SalesReservation $reservation, string $notes = 'Note'): array
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

    /** Scenario 1 & 8: same derived idempotency + same fingerprint → second confirm is replay; one domain row. */
    public function test_two_confirms_same_idempotency_and_fingerprint_second_is_replay_single_action_row(): void
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

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'idempotent_replay')
            ->assertJsonPath('data.controls.execution_replayed', true);

        $this->assertSame(1, SalesReservationAction::query()->where('action_type', 'credit_client_contact')->count());
    }

    /** Scenario 2: same idempotency key string with different fingerprint → conflict (simulated DB drift). */
    public function test_two_confirms_same_idempotency_different_fingerprint_yields_conflict(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $stable = app(CreditClientContactProposalBinder::class)->deriveStableIdempotencyKey($proposal);
        $this->assertNotNull($stable);

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

        SafeWriteExecutionOutcome::query()
            ->where('user_id', $user->id)
            ->where('action_key', 'credit_booking.client_contact.log')
            ->where('idempotency_key', $stable)
            ->update(['proposal_fingerprint' => str_repeat('f', 64)]);

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'idempotency_conflict')
            ->assertJsonPath('data.result.binding_reason', 'idempotency.fingerprint_mismatch');
    }

    /** Scenario 3: permission removed after preview → authorizeAction fails (no execution). */
    public function test_confirm_after_permission_revoke_before_executor_is_forbidden(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $token = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        $user->revokePermissionTo('credit.bookings.manage');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertForbidden();

        $this->assertSame(0, SalesReservationAction::query()->where('action_type', 'credit_client_contact')->count());
    }

    /** Scenario 4: per-action intent flag turned off after preview. */
    public function test_confirm_after_credit_action_intent_flag_disabled_is_execution_disabled(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $token = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        config(['safe_write_intent.actions.credit_booking.client_contact.log' => false]);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.intent_gate_allowed', false)
            ->assertJsonPath('data.controls.intent_gate_reason', 'intent_gate.action_flag_disabled');

        $this->assertSame(0, SalesReservationAction::query()->where('action_type', 'credit_client_contact')->count());
    }

    /** Scenario 5a: expired commit token. */
    public function test_confirm_with_expired_proposal_commit_token_fails_closed(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $token = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        SafeWriteProposalCommit::query()
            ->where('commit_token', $token)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.commit_expired');
    }

    /** Scenario 5b: token issued to another user cannot be confirmed by a different user. */
    public function test_confirm_with_commit_token_bound_to_different_user_fails_closed(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);

        $alice = User::factory()->create();
        $alice->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($alice);

        $proposal = $this->proposalForReservation($reservation);
        $token = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        $bob = User::factory()->create();
        $bob->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($bob);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.unknown_commit_token');
    }

    /** Scenario 5c: second preview replaces commit; first token is no longer valid. */
    public function test_confirm_with_superseded_commit_token_after_second_preview_fails_closed(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);

        $tokenFirst = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        $tokenSecond = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        $this->assertNotSame($tokenFirst, $tokenSecond);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $tokenFirst,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.unknown_commit_token');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $tokenSecond,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'executed');
    }

    /** Scenario 6: notes changed after preview (same reservation) → unknown_commit_token (derived idempotency diverges). */
    public function test_confirm_with_tampered_notes_after_preview_fails_closed(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation, 'Original');
        $token = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        $tampered = $this->proposalForReservation($reservation, 'Tampered');

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $tampered,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.unknown_commit_token');
    }

    /** Scenario 7: path vs payload mismatch (integrity rule). */
    public function test_confirm_path_payload_reservation_mismatch_fails_closed(): void
    {
        $a = SalesReservation::factory()->create(['status' => 'confirmed']);
        $b = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($a);
        $proposal['flow']['handoff']['path'] = '/api/credit/bookings/'.$b->id.'/actions';

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed')
            ->assertJsonPath('data.result.binding_reason', 'proposal_binding.handoff_path_reservation_mismatch');
    }

    /** Scenario 9: replay and binding failures still append safe_write_confirm audit rows. */
    public function test_replay_and_binding_failure_still_write_safe_write_confirm_audit(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view', 'credit.bookings.manage']);
        Sanctum::actingAs($user);

        $proposal = $this->proposalForReservation($reservation);
        $token = $this->postJson('/api/ai/write-actions/preview', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $proposal,
        ])->json('data.controls.proposal_commit_token');

        $payload = [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $proposal,
            'confirmation_phrase' => 'confirm_draft_only',
        ];

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk();
        $afterFirst = AiAuditEntry::query()->where('action', 'safe_write_confirm')->count();

        $this->postJson('/api/ai/write-actions/confirm', $payload)->assertOk()
            ->assertJsonPath('data.status', 'idempotent_replay');
        $afterReplay = AiAuditEntry::query()->where('action', 'safe_write_confirm')->count();
        $this->assertSame($afterFirst + 1, $afterReplay);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal_commit_token' => $token,
            'proposal' => $this->proposalForReservation($reservation, 'other-notes'),
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposal_binding_failed');

        $this->assertSame($afterReplay + 1, AiAuditEntry::query()->where('action', 'safe_write_confirm')->count());
    }

    /** Scenario 10: task.create remains non-executable when credit is allowlisted. */
    public function test_task_create_confirm_stays_execution_disabled_during_credit_intent_window(): void
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
            ->assertJsonPath('data.status', 'execution_disabled')
            ->assertJsonPath('data.controls.execution_enabled', false);
    }

    /** 403 path does not emit safe_write_confirm (authorization short-circuits). */
    public function test_forbidden_confirm_does_not_append_safe_write_confirm_audit(): void
    {
        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $user = User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'credit.bookings.view']);
        Sanctum::actingAs($user);

        $before = AiAuditEntry::query()->where('action', 'safe_write_confirm')->count();

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'credit_booking.client_contact.log',
            'proposal' => $this->proposalForReservation($reservation),
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertForbidden();

        $this->assertSame($before, AiAuditEntry::query()->where('action', 'safe_write_confirm')->count());
    }
}
