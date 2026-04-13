<?php

namespace App\Services\AI\SafeWrites;

/**
 * Deterministic pre-execution barrier for SafeWriteActionService::confirm.
 * Not mutation policy, not record authorization, not execution.
 */
class SafeWriteIntentGate
{
    /**
     * @param  array<string, mixed>  $action  From SafeWriteActionRegistry::find()
     */
    public function evaluate(array $action): SafeWriteIntentDecision
    {
        $key = (string) ($action['key'] ?? '');
        if ($key === '') {
            return $this->deny('intent_gate.missing_action_key');
        }

        if (! config('safe_write_intent.enabled', false)) {
            return $this->deny('intent_gate.global_disabled');
        }

        $allowlist = (array) config('safe_write_intent.allowlisted_keys', []);
        if (! in_array($key, $allowlist, true)) {
            return $this->deny('intent_gate.not_allowlisted', ['key' => $key]);
        }

        if (! ($action['intent_execution_candidate'] ?? false)) {
            return $this->deny('intent_gate.not_execution_candidate', ['key' => $key]);
        }

        $actionFlag = (bool) config('safe_write_intent.actions.'.$key, false);
        if (! $actionFlag) {
            return $this->deny('intent_gate.action_flag_disabled', ['key' => $key]);
        }

        $state = (string) ($action['activation_state'] ?? '');
        if (in_array($state, ['forbidden', 'not_safe'], true)) {
            return $this->deny('intent_gate.activation_state_blocked', ['activation_state' => $state]);
        }

        if ($state === 'draft_only') {
            // Only the explicit candidate may pass; enforced by intent_execution_candidate + allowlist.
        } elseif (in_array($state, ['confirmation_only_disabled'], true)) {
            return $this->deny('intent_gate.activation_state_blocked', ['activation_state' => $state]);
        }

        if (! $this->registryMetadataValidForIntent($action)) {
            return $this->deny('intent_gate.invalid_registry_metadata', ['key' => $key]);
        }

        return new SafeWriteIntentDecision(true, 'intent_gate.allowed', ['key' => $key]);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function registryMetadataValidForIntent(array $action): bool
    {
        $handler = (string) ($action['handler'] ?? '');
        $draftFlowKey = (string) ($action['draft_flow_key'] ?? '');
        $surface = (string) ($action['reusable_surface'] ?? '');

        return $handler !== ''
            && class_exists($handler)
            && $draftFlowKey !== ''
            && $surface !== '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function deny(string $reason, array $context = []): SafeWriteIntentDecision
    {
        return new SafeWriteIntentDecision(false, $reason, $context);
    }
}
