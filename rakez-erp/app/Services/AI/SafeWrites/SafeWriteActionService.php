<?php

namespace App\Services\AI\SafeWrites;

use App\Models\AiAuditEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SafeWriteActionService
{
    public function __construct(
        private readonly SafeWriteActionRegistry $registry,
        private readonly Container $container,
        private readonly SafeWriteIntentGate $intentGate,
        private readonly SafeWriteMutationPolicy $mutationPolicy,
        private readonly SafeWriteCreditClientContactExecutor $creditClientContactExecutor,
        private readonly CreditClientContactProposalBinder $creditClientContactBinder,
        private readonly SafeWriteSalesReservationActionExecutor $salesReservationActionExecutor,
        private readonly SalesReservationActionProposalBinder $salesReservationActionBinder,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(User $user): array
    {
        return $this->registry->catalogForUser($user);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function propose(User $user, string $actionKey, array $input): array
    {
        $action = $this->authorizeAction($user, $actionKey);
        $normalizedInput = $this->normalizeInput($user, $action, $input);
        $result = $this->handler($action)->propose($user, $action, $normalizedInput);

        $extraControls = [];
        if ($actionKey === CreditClientContactProposalBinder::ACTION_KEY) {
            $proposal = (array) ($result['proposal'] ?? []);
            $stableIdem = $this->creditClientContactBinder->deriveStableIdempotencyKey($proposal);
            if ($stableIdem !== null) {
                $token = $this->creditClientContactExecutor->recordProposalCommit(
                    $user,
                    $stableIdem,
                    $proposal
                );
                if ($token !== null) {
                    $extraControls['proposal_commit_token'] = $token;
                }
            }
        } elseif ($actionKey === SalesReservationActionProposalBinder::ACTION_KEY) {
            $proposal = (array) ($result['proposal'] ?? []);
            $stableIdem = $this->salesReservationActionBinder->deriveStableIdempotencyKey($proposal);
            if ($stableIdem !== null) {
                $token = $this->salesReservationActionExecutor->recordProposalCommit(
                    $user,
                    $stableIdem,
                    $proposal
                );
                if ($token !== null) {
                    $extraControls['proposal_commit_token'] = $token;
                }
            }
        }

        return $this->wrap('propose', $user, $action, $result, array_merge($normalizedInput, [
            'action_key' => $actionKey,
        ]), null, null, $extraControls);
    }

    /**
     * @param  array<string, mixed>  $validated  Includes proposal and optional idempotency / commit token for credit flow
     * @return array<string, mixed>
     */
    public function preview(User $user, string $actionKey, array $validated): array
    {
        $action = $this->authorizeAction($user, $actionKey);
        $proposal = (array) ($validated['proposal'] ?? []);
        $result = $this->handler($action)->preview($user, $action, $proposal);

        $extraControls = [];
        $stableIdem = null;
        if ($actionKey === CreditClientContactProposalBinder::ACTION_KEY) {
            $stableIdem = $this->creditClientContactBinder->deriveStableIdempotencyKey($proposal);
            $tok = (string) ($validated['proposal_commit_token'] ?? '');
            if ($stableIdem !== null) {
                $clientIdem = (string) ($validated['idempotency_key'] ?? '');
                if ($clientIdem !== '' && ! hash_equals($stableIdem, $clientIdem)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['The idempotency key does not match the canonical proposal binding for this action.'],
                    ]);
                }
                if ($tok !== '') {
                    $this->creditClientContactExecutor->refreshProposalCommit($user, $stableIdem, $tok, $proposal);
                    $extraControls['proposal_commit_token'] = $tok;
                } else {
                    $newTok = $this->creditClientContactExecutor->recordProposalCommit($user, $stableIdem, $proposal);
                    if ($newTok !== null) {
                        $extraControls['proposal_commit_token'] = $newTok;
                    }
                }
            }
        } elseif ($actionKey === SalesReservationActionProposalBinder::ACTION_KEY) {
            $stableIdem = $this->salesReservationActionBinder->deriveStableIdempotencyKey($proposal);
            $tok = (string) ($validated['proposal_commit_token'] ?? '');
            if ($stableIdem !== null) {
                $clientIdem = (string) ($validated['idempotency_key'] ?? '');
                if ($clientIdem !== '' && ! hash_equals($stableIdem, $clientIdem)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['The idempotency key does not match the canonical proposal binding for this action.'],
                    ]);
                }
                if ($tok !== '') {
                    $this->salesReservationActionExecutor->refreshProposalCommit($user, $stableIdem, $tok, $proposal);
                    $extraControls['proposal_commit_token'] = $tok;
                } else {
                    $newTok = $this->salesReservationActionExecutor->recordProposalCommit($user, $stableIdem, $proposal);
                    if ($newTok !== null) {
                        $extraControls['proposal_commit_token'] = $newTok;
                    }
                }
            }
        }

        return $this->wrap('preview', $user, $action, $result, [
            'action_key' => $actionKey,
            'proposal' => $proposal,
            'idempotency_key' => $stableIdem,
        ], null, null, $extraControls);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function confirm(User $user, string $actionKey, array $input): array
    {
        $action = $this->authorizeAction($user, $actionKey);
        $input = $this->normalizeInput($user, $action, $input);
        $intentDecision = $this->intentGate->evaluate($action);
        $mutationDecision = $this->mutationPolicy->evaluate($user, $actionKey, $input);

        $creditExecutable = $actionKey === CreditClientContactProposalBinder::ACTION_KEY
            && $intentDecision->allowed
            && $mutationDecision->allowed;

        $salesReservationExecutable = $actionKey === SalesReservationActionProposalBinder::ACTION_KEY
            && $intentDecision->allowed
            && $mutationDecision->allowed;

        if ($creditExecutable) {
            $execResult = $this->creditClientContactExecutor->run($user, $input);

            return $this->wrapConfirmCreditExecution(
                $user,
                $action,
                $input,
                $intentDecision,
                $mutationDecision,
                $execResult
            );
        }

        if ($salesReservationExecutable) {
            $execResult = $this->salesReservationActionExecutor->run($user, $input);

            return $this->wrapConfirmSalesReservationActionExecution(
                $user,
                $action,
                $input,
                $intentDecision,
                $mutationDecision,
                $execResult
            );
        }

        $result = $this->handler($action)->confirm($user, $action, $input);

        return $this->wrap('confirm', $user, $action, $result, array_merge($input, [
            'action_key' => $actionKey,
        ]), $intentDecision, $mutationDecision, []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function reject(User $user, string $actionKey, array $input): array
    {
        $action = $this->authorizeAction($user, $actionKey);
        $result = $this->handler($action)->reject($user, $action, $input);

        return $this->wrap('reject', $user, $action, $result, array_merge($input, [
            'action_key' => $actionKey,
        ]), null, null, []);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    protected function handler(array $action): object
    {
        return $this->container->make($action['handler']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeInput(User $user, array $action, array $input): array
    {
        if (($action['key'] ?? '') === CreditClientContactProposalBinder::ACTION_KEY
            && isset($input['proposal']) && is_array($input['proposal'])) {
            return $this->normalizeCreditClientContactConfirmInput($user, $input);
        }

        if (($action['key'] ?? '') === SalesReservationActionProposalBinder::ACTION_KEY
            && isset($input['proposal']) && is_array($input['proposal'])) {
            return $this->normalizeSalesReservationActionConfirmInput($user, $input);
        }

        $idempotencyKey = (string) ($input['idempotency_key'] ?? '');

        if ($idempotencyKey === '') {
            $source = json_encode([
                'action_key' => $action['key'],
                'user_id' => $user->id,
                'message' => $input['message'] ?? null,
                'payload' => $input['payload'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $idempotencyKey = 'safe-write:' . Str::lower($action['key']) . ':' . sha1((string) $source);
        }

        return array_merge($input, [
            'dry_run' => (bool) ($input['dry_run'] ?? true),
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Credit confirm only: idempotency is the canonical binding hash — never the generic message-based default.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeCreditClientContactConfirmInput(User $user, array $input): array
    {
        $proposal = (array) ($input['proposal'] ?? []);
        $stable = $this->creditClientContactBinder->deriveStableIdempotencyKey($proposal);
        $client = (string) ($input['idempotency_key'] ?? '');

        if ($stable === null) {
            if ($client !== '') {
                throw ValidationException::withMessages([
                    'idempotency_key' => ['The idempotency key cannot be honored because the proposal does not satisfy the binding contract for this action.'],
                ]);
            }

            return array_merge($input, [
                'dry_run' => (bool) ($input['dry_run'] ?? true),
                'idempotency_key' => '',
            ]);
        }

        if ($client !== '' && ! hash_equals($stable, $client)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['The idempotency key does not match the canonical proposal binding for this action.'],
            ]);
        }

        return array_merge($input, [
            'dry_run' => (bool) ($input['dry_run'] ?? true),
            'idempotency_key' => $stable,
        ]);
    }

    /**
     * sales_reservation.action.log confirm only: idempotency from canonical binding hash (sw_sra:…).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeSalesReservationActionConfirmInput(User $user, array $input): array
    {
        $proposal = (array) ($input['proposal'] ?? []);
        $stable = $this->salesReservationActionBinder->deriveStableIdempotencyKey($proposal);
        $client = (string) ($input['idempotency_key'] ?? '');

        if ($stable === null) {
            if ($client !== '') {
                throw ValidationException::withMessages([
                    'idempotency_key' => ['The idempotency key cannot be honored because the proposal does not satisfy the binding contract for this action.'],
                ]);
            }

            return array_merge($input, [
                'dry_run' => (bool) ($input['dry_run'] ?? true),
                'idempotency_key' => '',
            ]);
        }

        if ($client !== '' && ! hash_equals($stable, $client)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['The idempotency key does not match the canonical proposal binding for this action.'],
            ]);
        }

        return array_merge($input, [
            'dry_run' => (bool) ($input['dry_run'] ?? true),
            'idempotency_key' => $stable,
        ]);
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function wrapConfirmSalesReservationActionExecution(
        User $user,
        array $action,
        array $input,
        SafeWriteIntentDecision $intentDecision,
        SafeWriteMutationPolicyDecision $mutationDecision,
        array $execResult,
    ): array {
        $status = (string) ($execResult['status'] ?? 'execution_failed');
        $intentForAudit = $intentDecision->toArray();
        $mutationForAudit = $mutationDecision->toArray();

        $executionAudit = [
            'phase' => 'sales_reservation_action',
            'executor_status' => $status,
        ];
        if (in_array($status, ['executed', 'idempotent_replay'], true)) {
            $executionAudit['sales_reservation_action_id'] = $execResult['sales_reservation_action_id'] ?? null;
            $executionAudit['sales_reservation_id'] = $execResult['sales_reservation_id'] ?? null;
            $executionAudit['action_type'] = $execResult['action_type'] ?? null;
        }
        if ($status === 'idempotent_replay') {
            $executionAudit['replay'] = true;
        }
        if (in_array($status, ['proposal_binding_failed', 'idempotency_conflict', 'execution_failed'], true)) {
            $executionAudit['reason'] = $execResult['reason'] ?? null;
        }

        $auditEntry = AiAuditEntry::create([
            'user_id' => $user->id,
            'correlation_id' => (string) Str::uuid(),
            'action' => 'safe_write_confirm',
            'resource_type' => 'safe_write_action',
            'resource_id' => $action['key'],
            'input_summary' => json_encode($this->auditInput($input), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'output_summary' => json_encode([
                'status' => $status,
                'classification' => $action['classification'],
                'activation_state' => $action['activation_state'],
                'intent_gate' => $intentForAudit,
                'mutation_policy' => $mutationForAudit,
                'execution' => $executionAudit,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => request()->ip(),
        ]);

        $executedFresh = $status === 'executed';
        $replay = $status === 'idempotent_replay';

        $controls = [
            'dry_run' => ! $executedFresh,
            'confirmation_required' => true,
            'execution_enabled' => $executedFresh,
            'execution_replayed' => $replay,
            'confirmation_phrase_required' => true,
            'idempotency_key' => $input['idempotency_key'] ?? null,
            'intent_gate_allowed' => $intentDecision->allowed,
            'intent_gate_reason' => $intentDecision->reason,
            'mutation_policy_allowed' => $mutationDecision->allowed,
            'mutation_policy_reason' => $mutationDecision->reason,
        ];

        $resultPayload = [
            'message' => $executedFresh
                ? 'Sales reservation action logged.'
                : ($replay
                    ? 'Idempotent replay: no duplicate write.'
                    : 'Execution did not complete.'),
            'sales_reservation_action_id' => $execResult['sales_reservation_action_id'] ?? null,
            'sales_reservation_id' => $execResult['sales_reservation_id'] ?? null,
            'action_type' => $execResult['action_type'] ?? null,
            'binding_reason' => $execResult['reason'] ?? null,
        ];

        return [
            'status' => $status,
            'stage' => 'confirm',
            'action' => Arr::except($action, ['handler', 'draft_flow_key']),
            'controls' => $controls,
            'result' => $resultPayload,
            'audit_entry_id' => $auditEntry->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function wrapConfirmCreditExecution(
        User $user,
        array $action,
        array $input,
        SafeWriteIntentDecision $intentDecision,
        SafeWriteMutationPolicyDecision $mutationDecision,
        array $execResult,
    ): array {
        $status = (string) ($execResult['status'] ?? 'execution_failed');
        $intentForAudit = $intentDecision->toArray();
        $mutationForAudit = $mutationDecision->toArray();

        $executionAudit = [
            'phase' => 'credit_client_contact',
            'executor_status' => $status,
        ];
        if ($status === 'executed') {
            $executionAudit['sales_reservation_action_id'] = $execResult['sales_reservation_action_id'] ?? null;
            $executionAudit['sales_reservation_id'] = $execResult['sales_reservation_id'] ?? null;
        }
        if ($status === 'idempotent_replay') {
            $executionAudit['sales_reservation_action_id'] = $execResult['sales_reservation_action_id'] ?? null;
            $executionAudit['replay'] = true;
        }
        if (in_array($status, ['proposal_binding_failed', 'idempotency_conflict', 'execution_failed'], true)) {
            $executionAudit['reason'] = $execResult['reason'] ?? null;
        }

        $auditEntry = AiAuditEntry::create([
            'user_id' => $user->id,
            'correlation_id' => (string) Str::uuid(),
            'action' => 'safe_write_confirm',
            'resource_type' => 'safe_write_action',
            'resource_id' => $action['key'],
            'input_summary' => json_encode($this->auditInput($input), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'output_summary' => json_encode([
                'status' => $status,
                'classification' => $action['classification'],
                'activation_state' => $action['activation_state'],
                'intent_gate' => $intentForAudit,
                'mutation_policy' => $mutationForAudit,
                'execution' => $executionAudit,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => request()->ip(),
        ]);

        $executedFresh = $status === 'executed';
        $replay = $status === 'idempotent_replay';

        $controls = [
            'dry_run' => ! $executedFresh,
            'confirmation_required' => true,
            'execution_enabled' => $executedFresh,
            'execution_replayed' => $replay,
            'confirmation_phrase_required' => true,
            'idempotency_key' => $input['idempotency_key'] ?? null,
            'intent_gate_allowed' => $intentDecision->allowed,
            'intent_gate_reason' => $intentDecision->reason,
            'mutation_policy_allowed' => $mutationDecision->allowed,
            'mutation_policy_reason' => $mutationDecision->reason,
        ];

        $resultPayload = [
            'message' => $executedFresh
                ? 'Credit client contact logged.'
                : ($replay
                    ? 'Idempotent replay: no duplicate write.'
                    : 'Execution did not complete.'),
            'sales_reservation_action_id' => $execResult['sales_reservation_action_id'] ?? null,
            'sales_reservation_id' => $execResult['sales_reservation_id'] ?? null,
            'binding_reason' => $execResult['reason'] ?? null,
        ];

        return [
            'status' => $status,
            'stage' => 'confirm',
            'action' => Arr::except($action, ['handler', 'draft_flow_key']),
            'controls' => $controls,
            'result' => $resultPayload,
            'audit_entry_id' => $auditEntry->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $extraControls
     * @return array<string, mixed>
     */
    protected function wrap(string $stage, User $user, array $action, array $result, array $input, ?SafeWriteIntentDecision $intentDecision = null, ?SafeWriteMutationPolicyDecision $mutationDecision = null, array $extraControls = []): array
    {
        $intentForAudit = $intentDecision?->toArray() ?? [
            'evaluated' => false,
            'allowed' => false,
            'reason' => 'intent_gate.stage_not_confirm',
        ];

        $mutationForAudit = $mutationDecision?->toArray() ?? [
            'evaluated' => false,
            'allowed' => false,
            'reason' => 'mutation_policy.stage_not_confirm',
        ];

        $auditEntry = AiAuditEntry::create([
            'user_id' => $user->id,
            'correlation_id' => (string) Str::uuid(),
            'action' => 'safe_write_' . $stage,
            'resource_type' => 'safe_write_action',
            'resource_id' => $action['key'],
            'input_summary' => json_encode($this->auditInput($input), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'output_summary' => json_encode([
                'status' => $result['status'] ?? null,
                'classification' => $action['classification'],
                'activation_state' => $action['activation_state'],
                'intent_gate' => $intentForAudit,
                'mutation_policy' => $mutationForAudit,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => request()->ip(),
        ]);

        $controls = [
            'dry_run' => true,
            'confirmation_required' => true,
            'execution_enabled' => false,
            'confirmation_phrase_required' => $stage === 'confirm',
            'idempotency_key' => $input['idempotency_key'] ?? null,
        ];

        $controls = array_merge($controls, $extraControls);

        if ($stage === 'confirm' && $intentDecision !== null) {
            $controls['intent_gate_allowed'] = $intentDecision->allowed;
            $controls['intent_gate_reason'] = $intentDecision->reason;
        }

        if ($stage === 'confirm' && $mutationDecision !== null) {
            $controls['mutation_policy_allowed'] = $mutationDecision->allowed;
            $controls['mutation_policy_reason'] = $mutationDecision->reason;
        }

        return [
            'status' => $result['status'] ?? null,
            'stage' => $stage,
            'action' => Arr::except($action, ['handler', 'draft_flow_key']),
            'controls' => $controls,
            'result' => Arr::except($result, ['status']),
            'audit_entry_id' => $auditEntry->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function auditInput(array $input): array
    {
        return [
            'action_key' => $input['action_key'] ?? null,
            'proposal_id' => $input['proposal_id'] ?? null,
            'proposal_commit_token_present' => ! empty($input['proposal_commit_token']),
            'dry_run' => $input['dry_run'] ?? true,
            'idempotency_key' => $input['idempotency_key'] ?? null,
            'message_present' => ! empty($input['message']),
            'payload_keys' => array_keys((array) ($input['payload'] ?? [])),
            'proposal_keys' => array_keys((array) ($input['proposal'] ?? [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function authorizeAction(User $user, string $actionKey): array
    {
        $action = $this->registry->find($actionKey);

        if (!$action) {
            throw new AuthorizationException('Unknown safe write action.');
        }

        $permission = $action['permission'] ?? null;
        if ($permission && $permission !== 'use-ai-assistant' && !$user->can($permission)) {
            throw new AuthorizationException('You do not have permission to access this write action.');
        }

        return $action;
    }
}
