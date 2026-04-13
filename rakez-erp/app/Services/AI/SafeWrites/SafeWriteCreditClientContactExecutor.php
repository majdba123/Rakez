<?php

namespace App\Services\AI\SafeWrites;

use App\Models\SafeWriteExecutionOutcome;
use App\Models\SafeWriteProposalCommit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\Sales\SalesReservationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single-action executor for credit_booking.client_contact.log — no generic action dispatch.
 */
class SafeWriteCreditClientContactExecutor
{
    public function __construct(
        private readonly CreditClientContactProposalBinder $binder,
        private readonly SalesReservationService $reservationService,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Confirm payload (includes proposal, idempotency_key, proposal_commit_token)
     * @return array<string, mixed>
     */
    public function run(User $user, array $input): array
    {
        $proposal = (array) ($input['proposal'] ?? []);
        $shapeError = $this->binder->validateBindingShape($proposal);
        if ($shapeError !== null) {
            return ['status' => 'proposal_binding_failed', 'reason' => $shapeError];
        }

        $fingerprint = $this->binder->fingerprint($proposal);
        $idempotencyKey = (string) ($input['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            return ['status' => 'proposal_binding_failed', 'reason' => 'proposal_binding.missing_idempotency_key'];
        }

        $token = (string) ($input['proposal_commit_token'] ?? '');
        if ($token === '' || strlen($token) !== 64) {
            return ['status' => 'proposal_binding_failed', 'reason' => 'proposal_binding.missing_commit_token'];
        }

        $commit = SafeWriteProposalCommit::query()
            ->where('commit_token', $token)
            ->where('user_id', $user->id)
            ->where('action_key', CreditClientContactProposalBinder::ACTION_KEY)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($commit === null) {
            return ['status' => 'proposal_binding_failed', 'reason' => 'proposal_binding.unknown_commit_token'];
        }

        if ($commit->expires_at->isPast()) {
            return ['status' => 'proposal_binding_failed', 'reason' => 'proposal_binding.commit_expired'];
        }

        if (! hash_equals($commit->proposal_fingerprint, $fingerprint)) {
            return ['status' => 'proposal_binding_failed', 'reason' => 'proposal_binding.fingerprint_mismatch'];
        }

        $reservationId = (int) $this->binder->bindingPayload($proposal)['sales_reservation_id'];
        $notes = $this->binder->extractNotes($proposal);

        try {
            $txResult = DB::transaction(function () use ($user, $idempotencyKey, $fingerprint, $reservationId, $notes) {
                SalesReservation::query()
                    ->whereKey($reservationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = SafeWriteExecutionOutcome::query()
                    ->where('user_id', $user->id)
                    ->where('action_key', CreditClientContactProposalBinder::ACTION_KEY)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals($existing->proposal_fingerprint, $fingerprint)) {
                        return ['status' => 'idempotency_conflict', 'reason' => 'idempotency.fingerprint_mismatch'];
                    }

                    return [
                        'status' => 'idempotent_replay',
                        'sales_reservation_action_id' => $existing->sales_reservation_action_id,
                    ];
                }

                $created = $this->reservationService->logCreditClientContact($reservationId, $notes, $user);

                SafeWriteExecutionOutcome::create([
                    'user_id' => $user->id,
                    'action_key' => CreditClientContactProposalBinder::ACTION_KEY,
                    'idempotency_key' => $idempotencyKey,
                    'proposal_fingerprint' => $fingerprint,
                    'sales_reservation_action_id' => $created->id,
                ]);

                return [
                    'status' => 'executed',
                    'sales_reservation_action_id' => $created->id,
                    'sales_reservation_id' => $reservationId,
                ];
            });
        } catch (ModelNotFoundException) {
            return [
                'status' => 'execution_failed',
                'reason' => 'execution.reservation_not_found',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'execution_failed',
                'reason' => 'execution.domain_error',
                'exception_class' => $e::class,
            ];
        }

        return $txResult;
    }

    /**
     * Update fingerprint for an existing commit after preview (same token).
     *
     * @param  array<string, mixed>  $proposal
     */
    public function refreshProposalCommit(User $user, string $idempotencyKey, string $commitToken, array $proposal): bool
    {
        $shapeError = $this->binder->validateBindingShape($proposal);
        if ($shapeError !== null) {
            return false;
        }

        $commit = SafeWriteProposalCommit::query()
            ->where('commit_token', $commitToken)
            ->where('user_id', $user->id)
            ->where('action_key', CreditClientContactProposalBinder::ACTION_KEY)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($commit === null || $commit->expires_at->isPast()) {
            return false;
        }

        $commit->proposal_fingerprint = $this->binder->fingerprint($proposal);
        $commit->expires_at = now()->addHours(24);
        $commit->save();

        return true;
    }

    /**
     * Register or replace a proposal commit after propose/preview for this action.
     *
     * @param  array<string, mixed>  $proposal
     */
    public function recordProposalCommit(User $user, string $idempotencyKey, array $proposal): ?string
    {
        $shapeError = $this->binder->validateBindingShape($proposal);
        if ($shapeError !== null) {
            return null;
        }

        $fingerprint = $this->binder->fingerprint($proposal);

        SafeWriteProposalCommit::query()
            ->where('user_id', $user->id)
            ->where('action_key', CreditClientContactProposalBinder::ACTION_KEY)
            ->where('idempotency_key', $idempotencyKey)
            ->delete();

        $token = Str::random(64);

        SafeWriteProposalCommit::create([
            'user_id' => $user->id,
            'action_key' => CreditClientContactProposalBinder::ACTION_KEY,
            'idempotency_key' => $idempotencyKey,
            'commit_token' => $token,
            'proposal_fingerprint' => $fingerprint,
            'expires_at' => now()->addHours(24),
        ]);

        return $token;
    }
}
